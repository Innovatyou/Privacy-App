package com.innovatyou.privacydisplay.service

import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.content.pm.ServiceInfo
import android.text.format.DateFormat
import java.util.Date
import android.os.Build
import android.os.SystemClock
import android.util.Log
import androidx.core.app.ServiceCompat
import androidx.lifecycle.LifecycleService
import androidx.lifecycle.lifecycleScope
import com.innovatyou.privacydisplay.R
import com.innovatyou.privacydisplay.camera.FaceDetectionManager
import com.innovatyou.privacydisplay.camera.ViewerReport
import com.innovatyou.privacydisplay.camera.ViewerSide
import com.innovatyou.privacydisplay.camera.ViewerState
import com.innovatyou.privacydisplay.data.PreferencesRepository
import com.innovatyou.privacydisplay.data.PrivacySettings
import com.innovatyou.privacydisplay.overlay.PrivacyPolicy
import com.innovatyou.privacydisplay.owner.OwnerDecision
import com.innovatyou.privacydisplay.owner.OwnerFaceStore
import com.innovatyou.privacydisplay.owner.OwnerPolicy
import com.innovatyou.privacydisplay.ui.UnlockActivity
import com.innovatyou.privacydisplay.util.BlurSupport
import com.innovatyou.privacydisplay.util.DeviceState
import com.innovatyou.privacydisplay.util.DeviceStateMonitor
import com.innovatyou.privacydisplay.util.ForegroundAppMonitor
import com.innovatyou.privacydisplay.util.OrientationManager
import com.innovatyou.privacydisplay.util.PermissionManager
import dagger.hilt.android.AndroidEntryPoint
import javax.inject.Inject
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.filterNotNull
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.flatMapLatest
import kotlinx.coroutines.flow.emitAll
import kotlinx.coroutines.flow.flow
import kotlinx.coroutines.flow.flowOf
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch

/**
 * Foreground service that keeps the privacy mask on screen while other apps are in use.
 *
 * It runs while Privacy Mode is on, or in standby while "auto-enable on screen unlock" is on
 * (unlock broadcasts only reach running components). Everything is driven by flows: settings,
 * screen/battery state, the app in front and face detection are combined into what to draw.
 * The camera is only used while face detection is enabled and the mask is actually showing.
 */
@AndroidEntryPoint
class PrivacyOverlayService : LifecycleService() {

    @Inject lateinit var repository: PreferencesRepository
    @Inject lateinit var runtime: PrivacyRuntime
    @Inject lateinit var notifications: PrivacyNotification
    @Inject lateinit var faceDetection: FaceDetectionManager
    @Inject lateinit var foregroundApps: ForegroundAppMonitor
    @Inject lateinit var deviceMonitor: DeviceStateMonitor
    @Inject lateinit var orientation: OrientationManager
    @Inject lateinit var permissions: PermissionManager
    @Inject lateinit var blurSupport: BlurSupport
    @Inject lateinit var controller: PrivacyController
    @Inject lateinit var ownerFaces: OwnerFaceStore

    private var overlay: PrivacyOverlayWindow? = null
    private var shieldWindow: BlurShieldWindow? = null
    private var shareButton: ShareButtonWindow? = null
    private var blockScreen: BlockScreenWindow? = null
    private var lightRing: LightRingWindow? = null
    /** True while owner protection is active; actions that weaken it then need a fingerprint/PIN. */
    private var ownerProtected = false
    private var status: StatusContent? = null
    private var cameraTypeGranted = false
    private var alertShowing = false
    private val timeFormat by lazy { DateFormat.getTimeFormat(this) }
    private var lastAlertAt = 0L

    override fun onCreate() {
        super.onCreate()
        notifications.createChannels()
        runtime.update { it.copy(serviceRunning = true) }
        observe()
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        super.onStartCommand(intent, flags, startId)
        // Must be called promptly after every startForegroundService().
        startInForeground(withCamera = cameraTypeGranted)
        when (intent?.action) {
            ACTION_DISABLE ->
                if (ownerProtected) {
                    openUnlock(UnlockActivity.Mode.DISABLE)
                } else {
                    lifecycleScope.launch { repository.update { it.copy(enabled = false) } }
                }
            ACTION_ENABLE -> lifecycleScope.launch { repository.update { it.copy(enabled = true) } }
            ACTION_SHARE -> share()
            ACTION_STOP_SHARING -> controller.stopSharing()
        }
        return START_STICKY
    }

    override fun onDestroy() {
        lightRing?.hide()
        lightRing = null
        blockScreen?.hide()
        blockScreen = null
        hideShield()
        shareButton?.hide()
        shareButton = null
        overlay?.hide()
        overlay = null
        notifications.cancelViewerAlert()
        runtime.update { PrivacyRuntimeState() }
        super.onDestroy() // Cancels lifecycleScope, which also stops the camera.
    }

    @OptIn(ExperimentalCoroutinesApi::class)
    private fun observe() {
        val scope = lifecycleScope
        val settings: StateFlow<PrivacySettings?> =
            repository.settings.stateIn(scope, SharingStarted.Eagerly, null)
        val settingsFlow = settings.filterNotNull()
        val device: StateFlow<DeviceState> =
            deviceMonitor.deviceState().stateIn(scope, SharingStarted.Eagerly, deviceMonitor.current())

        // Stop when neither Privacy Mode nor auto-enable needs the service.
        scope.launch {
            settingsFlow.collect { if (!it.needsService) stopSelf() }
        }

        // Auto-enable on unlock.
        scope.launch {
            deviceMonitor.unlockEvents().collect {
                if (settingsFlow.first().autoEnableOnUnlock) repository.update { it.copy(enabled = true) }
            }
        }

        // Resize the mask when the display rotates or changes size.
        scope.launch {
            orientation.displayChanges().collect {
                overlay?.onDisplayChanged()
                blockScreen?.onDisplayChanged()
                lightRing?.onDisplayChanged()
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) shieldWindow?.onDisplayChanged()
            }
        }

        val pausedForPackage: Flow<String?> = combine(settingsFlow, device, runtime.appForegroundCount) { s, d, _ ->
            if (s.enabled && d.screenOn && s.excludedApps.isNotEmpty() && permissions.hasUsageAccess()) {
                ExclusionRequest(s.excludedApps, if (s.batterySaver) SLOW_POLL_MS else FAST_POLL_MS)
            } else {
                null
            }
        }.distinctUntilChanged().flatMapLatest { request ->
            if (request == null) {
                flowOf(null)
            } else {
                foregroundApps.foregroundPackages(request.intervalMs)
                    .map { pkg -> pkg?.takeIf { it in request.packages } }
            }
        }.distinctUntilChanged().stateIn(scope, SharingStarted.Eagerly, null)

        val sharing: Flow<Boolean> = runtime.sharingUntil.map { it != null }.distinctUntilChanged()
        val ownerActive: StateFlow<Boolean> = combine(settingsFlow, ownerFaces.enrolled) { s, enrolled ->
            OwnerPolicy.isActive(s, enrolled)
        }.distinctUntilChanged().stateIn(scope, SharingStarted.Eagerly, false)

        // Forget a fingerprint/PIN unlock once the screen turns off; leave owner mode cleanly.
        scope.launch {
            device.collect { if (!it.screenOn) runtime.setOwnerTrustedUntil(null) }
        }
        scope.launch {
            ownerActive.collect { active ->
                ownerProtected = active
                if (!active) runtime.setLocked(false)
            }
        }

        val cameraGates = combine(sharing, runtime.cameraBusy, ownerActive, runtime.locked) { isSharing, busy, owner, locked ->
            CameraGates(isSharing, busy, owner, locked)
        }
        val cameraDecision: Flow<CameraDecision> =
            combine(
                settingsFlow, device, pausedForPackage, runtime.appForegroundCount, cameraGates,
            ) { s, d, paused, appVisits, (isSharing, busy, owner, locked) ->
                when {
                    !s.enabled || !d.screenOn || isSharing || busy -> CameraDecision.Off
                    // Owner protection keeps checking even in ignored apps and in Battery Saver.
                    !s.faceDetectionEnabled && !owner -> CameraDecision.Off
                    paused != null && !owner -> CameraDecision.Off
                    s.batterySaver && d.powerSaveMode && !owner -> CameraDecision.PausedForBattery
                    !permissions.hasCameraPermission() -> CameraDecision.Unavailable
                    // Retry after the user opens the app, when Android allows camera use again.
                    else -> CameraDecision.Run(
                        intervalMs = when {
                            // While blocked, analyse quickly so the owner's blink is not missed.
                            owner && locked -> BLINK_ANALYSIS_MS
                            s.batterySaver -> SLOW_ANALYSIS_MS
                            else -> FAST_ANALYSIS_MS
                        },
                        retryKey = if (cameraTypeGranted) 0 else appVisits,
                        recognizeOwner = owner,
                        ownerIntervalMs = if (owner && locked) LOCKED_OWNER_CHECK_MS else OWNER_CHECK_MS,
                    )
                }
            }.distinctUntilChanged()

        val viewerReports: Flow<ViewerReport> = cameraDecision.flatMapLatest { decision ->
            when (decision) {
                CameraDecision.Off -> flowOf(ViewerReport(ViewerState.OFF))
                CameraDecision.PausedForBattery -> flowOf(ViewerReport(ViewerState.PAUSED_BATTERY))
                CameraDecision.Unavailable -> flowOf(ViewerReport(ViewerState.UNAVAILABLE))
                is CameraDecision.Run ->
                    if (ensureCameraForegroundType()) {
                        flow {
                            val facePrint = if (decision.recognizeOwner) ownerFaces.load() else null
                            emitAll(faceDetection.viewerReports(decision.intervalMs, facePrint, decision.ownerIntervalMs))
                        }
                    } else {
                        flowOf(ViewerReport(ViewerState.BLOCKED_IN_BACKGROUND))
                    }
            }
        }

        val ownerInputs = combine(ownerActive, runtime.locked, runtime.unlockScreenOpen, runtime.ownerTrustedUntil) {
                active, locked, unlockOpen, trustedUntil ->
            OwnerInput(active, locked, unlockOpen, trustedUntil)
        }
        scope.launch {
            combine(
                combine(settingsFlow, pausedForPackage, runtime.sharingUntil) { s, p, u -> Triple(s, p, u) },
                viewerReports,
                combine(blurSupport.availability(), runtime.shieldTest) { blur, test -> blur to test },
                ownerInputs,
            ) { (s, paused, sharingUntil), report, (blurAvailable, testing), owner ->
                RenderInput(s, paused, report, blurAvailable, testing, sharingUntil, owner)
            }.collect { render(it) }
        }
    }

    /** Locks the phone when a stranger is confirmed and shows or hides the block screen. */
    private fun updateOwnerLock(input: RenderInput) {
        val owner = input.owner
        val trusted = owner.trustedUntil != null && SystemClock.elapsedRealtime() < owner.trustedUntil
        if (owner.active) {
            val locked = OwnerPolicy.nextLocked(
                locked = owner.locked,
                decision = input.report.owner,
                ownerTrusted = trusted,
                blockWhenTooDark = input.settings.blockWhenTooDark,
                blinked = input.report.recentBlink,
            )
            if (locked != owner.locked) runtime.setLocked(locked)
        }
        // Low-light assist: light the owner's face while it is dim and recognition is running.
        val glow = owner.active && input.settings.lowLightAssist && input.report.lowLight &&
            input.sharingUntil == null && permissions.canDrawOverlays()
        if (glow) {
            try {
                (lightRing ?: LightRingWindow(this).also { lightRing = it }).show()
            } catch (e: RuntimeException) {
                Log.w(TAG, "Could not show the low-light glow", e)
                lightRing = null
            }
        } else {
            lightRing?.hide()
        }
        val show = permissions.canDrawOverlays() && OwnerPolicy.showBlockScreen(
            owner.active, owner.locked, input.sharingUntil != null, owner.unlockScreenOpen,
        )
        if (show) {
            try {
                (blockScreen ?: BlockScreenWindow(this) { openUnlock(UnlockActivity.Mode.UNLOCK) }
                    .also { blockScreen = it }).show()
            } catch (e: RuntimeException) {
                Log.w(TAG, "Could not show the block screen", e)
                blockScreen = null
            }
        } else {
            blockScreen?.hide()
        }
    }

    /** "Share screen": with owner protection, the owner has to confirm first. */
    private fun share() {
        if (ownerProtected) openUnlock(UnlockActivity.Mode.SHARE) else controller.startSharing()
    }

    private fun openUnlock(mode: UnlockActivity.Mode) {
        try {
            startActivity(UnlockActivity.intent(this, mode))
        } catch (e: RuntimeException) {
            Log.w(TAG, "Could not open the unlock screen", e)
        }
    }

    private fun render(input: RenderInput) {
        updateOwnerLock(input)
        val settings = input.settings
        val pausedForPackage = input.pausedForPackage
        val viewer = input.report.state
        val canDraw = permissions.canDrawOverlays()
        val sharing = input.sharingUntil != null
        val show = settings.enabled && pausedForPackage == null && !sharing && canDraw
        val shield = if (show) PrivacyPolicy.shieldReason(settings, viewer, input.shieldTest) else null
        val shieldBlurs = shield != null && input.blurAvailable
        val mask = PrivacyPolicy.shieldedMask(
            PrivacyPolicy.maskParams(settings, viewer), shield, input.blurAvailable,
        )

        if (show) {
            try {
                (overlay ?: PrivacyOverlayWindow(this).also { overlay = it }).show(mask)
            } catch (e: RuntimeException) {
                // For example WindowManager.BadTokenException if the permission was just revoked.
                Log.w(TAG, "Could not show the privacy overlay", e)
                overlay = null
            }
        } else {
            overlay?.hide()
        }
        if (shieldBlurs) {
            showShield(
                PrivacyPolicy.blurRadiusDp(settings.blurStrength),
                PrivacyPolicy.shieldVeilAlpha(settings.blurStrength),
            )
        } else {
            hideShield()
        }
        updateShareButton(show && PrivacyPolicy.offerSharing(settings, viewer, shield))
        val visible = overlay?.isShowing == true
        val pausedLabel = pausedForPackage?.let(::appLabel)

        runtime.update {
            it.copy(
                serviceRunning = true,
                overlayVisible = visible,
                pausedForApp = pausedLabel,
                viewerState = viewer,
                boosted = visible && mask.boosted,
                extraViewerSide = input.report.extraViewerSide,
                shield = shield.takeIf { visible },
                shieldBlurs = visible && shieldBlurs,
                ownerStatus = if (input.owner.active) input.report.owner else OwnerDecision.UNKNOWN,
                blocked = input.owner.active && input.owner.locked,
            )
        }
        updateViewerAlert(
            visible && settings.multipleViewerProtection && viewer == ViewerState.MULTIPLE_VIEWERS,
            input.report.extraViewerSide,
        )
        updateStatus(statusContent(settings, pausedLabel, input.report, canDraw, input.sharingUntil))
    }

    private fun updateShareButton(visible: Boolean) {
        if (!visible) {
            shareButton?.hide()
            return
        }
        try {
            (shareButton ?: ShareButtonWindow(this) { share() }.also { shareButton = it }).show()
        } catch (e: RuntimeException) {
            Log.w(TAG, "Could not show the share button", e)
            shareButton = null
        }
    }

    private fun showShield(radiusDp: Float, veilAlpha: Float) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.S) return
        try {
            (shieldWindow ?: BlurShieldWindow(this).also { shieldWindow = it }).show(radiusDp, veilAlpha)
        } catch (e: RuntimeException) {
            Log.w(TAG, "Could not show the viewer shield", e)
            shieldWindow = null
        }
    }

    private fun hideShield() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) shieldWindow?.hide()
    }

    private fun updateViewerAlert(multipleViewers: Boolean, side: ViewerSide?) {
        if (multipleViewers && !alertShowing) {
            alertShowing = true
            val now = SystemClock.elapsedRealtime()
            if (now - lastAlertAt > ALERT_MIN_INTERVAL_MS) {
                lastAlertAt = now
                notifications.showViewerAlert(side)
            }
        } else if (!multipleViewers && alertShowing) {
            alertShowing = false
            notifications.cancelViewerAlert()
        }
    }

    private fun statusContent(
        settings: PrivacySettings,
        pausedLabel: String?,
        report: ViewerReport,
        canDraw: Boolean,
        sharingUntil: Long?,
    ): StatusContent = when {
        settings.enabled && sharingUntil != null -> StatusContent(
            getString(R.string.sharing_title),
            getString(R.string.sharing_until, timeFormat.format(Date(sharingUntil))),
            privacyEnabled = true,
            sharing = true,
        )
        !settings.enabled -> StatusContent(
            getString(R.string.notif_standby_title), getString(R.string.notif_standby_text), false
        )
        !canDraw -> StatusContent(
            getString(R.string.notif_permission_title), getString(R.string.notif_permission_text), true
        )
        pausedLabel != null -> StatusContent(
            getString(R.string.notif_paused_title),
            getString(R.string.status_paused_for_app, pausedLabel),
            true,
        )
        else -> StatusContent(getString(R.string.notif_active_title), viewerText(report), true)
    }

    private fun viewerText(report: ViewerReport): String = getString(
        when (report.state) {
            ViewerState.MULTIPLE_VIEWERS -> when (report.extraViewerSide) {
                ViewerSide.LEFT -> R.string.alert_text_left
                ViewerSide.RIGHT -> R.string.alert_text_right
                ViewerSide.ABOVE -> R.string.alert_text_above
                ViewerSide.BELOW -> R.string.alert_text_below
                null -> R.string.status_additional_viewer
            }
            ViewerState.NO_FACE, ViewerState.LOOKING_AWAY -> R.string.status_no_viewer
            ViewerState.TOO_DARK -> R.string.status_too_dark_for_camera
            ViewerState.BLOCKED_IN_BACKGROUND -> R.string.face_paused_background
            ViewerState.PAUSED_BATTERY -> R.string.face_paused_battery
            ViewerState.UNAVAILABLE -> R.string.face_unavailable
            ViewerState.STARTING, ViewerState.USER_PRESENT -> R.string.face_active
            ViewerState.OFF -> R.string.notif_active_text
        }
    )

    private fun updateStatus(content: StatusContent) {
        if (content == status) return
        status = content
        notifications.updateStatus(content)
    }

    /**
     * Adds the camera type to the foreground service. Android 14+ only allows this while the app
     * is visible (or was visible when the service started); otherwise it throws.
     */
    private fun ensureCameraForegroundType(): Boolean {
        if (cameraTypeGranted) return true
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.R) {
            cameraTypeGranted = true
            return true
        }
        return try {
            startInForeground(withCamera = true)
            cameraTypeGranted = true
            true
        } catch (e: RuntimeException) {
            Log.i(TAG, "Camera not allowed from the background; waiting for the app to be opened", e)
            startInForeground(withCamera = false)
            false
        }
    }

    private fun startInForeground(withCamera: Boolean) {
        val notification = notifications.buildStatus(
            status ?: StatusContent(getString(R.string.app_name), getString(R.string.notif_starting), true)
        )
        val type = when {
            Build.VERSION.SDK_INT >= Build.VERSION_CODES.UPSIDE_DOWN_CAKE ->
                ServiceInfo.FOREGROUND_SERVICE_TYPE_SPECIAL_USE or
                    (if (withCamera) ServiceInfo.FOREGROUND_SERVICE_TYPE_CAMERA else 0)
            Build.VERSION.SDK_INT >= Build.VERSION_CODES.R && withCamera ->
                ServiceInfo.FOREGROUND_SERVICE_TYPE_CAMERA
            else -> null
        }
        if (type == null) {
            startForeground(PrivacyNotification.STATUS_ID, notification)
        } else {
            ServiceCompat.startForeground(this, PrivacyNotification.STATUS_ID, notification, type)
        }
    }

    private fun appLabel(packageName: String): String = try {
        val info = packageManager.getApplicationInfo(packageName, 0)
        packageManager.getApplicationLabel(info).toString()
    } catch (e: PackageManager.NameNotFoundException) {
        packageName
    }

    private data class ExclusionRequest(val packages: Set<String>, val intervalMs: Long)

    private data class RenderInput(
        val settings: PrivacySettings,
        val pausedForPackage: String?,
        val report: ViewerReport,
        val blurAvailable: Boolean,
        val shieldTest: Boolean,
        val sharingUntil: Long?,
        val owner: OwnerInput,
    )

    private data class CameraGates(val sharing: Boolean, val busy: Boolean, val owner: Boolean, val locked: Boolean)

    private data class OwnerInput(
        val active: Boolean,
        val locked: Boolean,
        val unlockScreenOpen: Boolean,
        val trustedUntil: Long?,
    )

    private sealed interface CameraDecision {
        data object Off : CameraDecision
        data object PausedForBattery : CameraDecision
        data object Unavailable : CameraDecision
        data class Run(
            val intervalMs: Long,
            val retryKey: Int,
            val recognizeOwner: Boolean,
            val ownerIntervalMs: Long,
        ) : CameraDecision
    }

    companion object {
        const val ACTION_DISABLE = "com.innovatyou.privacydisplay.action.DISABLE"
        const val ACTION_ENABLE = "com.innovatyou.privacydisplay.action.ENABLE"
        const val ACTION_SHARE = "com.innovatyou.privacydisplay.action.SHARE"
        const val ACTION_STOP_SHARING = "com.innovatyou.privacydisplay.action.STOP_SHARING"
        private const val TAG = "PrivacyOverlayService"

        /** Time between face detection runs. */
        private const val FAST_ANALYSIS_MS = 500L
        private const val SLOW_ANALYSIS_MS = 1_500L
        private const val BLINK_ANALYSIS_MS = 120L
        private const val OWNER_CHECK_MS = 1_000L
        private const val LOCKED_OWNER_CHECK_MS = 500L

        /** Time between foreground app checks when exclusions are set. */
        private const val FAST_POLL_MS = 1_000L
        private const val SLOW_POLL_MS = 2_000L

        private const val ALERT_MIN_INTERVAL_MS = 30_000L

        fun startIntent(context: Context) = Intent(context, PrivacyOverlayService::class.java)
    }
}
