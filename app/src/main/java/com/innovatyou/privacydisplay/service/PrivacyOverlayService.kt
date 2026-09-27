package com.innovatyou.privacydisplay.service

import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.content.pm.ServiceInfo
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

    private var overlay: PrivacyOverlayWindow? = null
    private var shieldWindow: BlurShieldWindow? = null
    private var status: StatusContent? = null
    private var cameraTypeGranted = false
    private var alertShowing = false
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
            ACTION_DISABLE -> lifecycleScope.launch { repository.update { it.copy(enabled = false) } }
            ACTION_ENABLE -> lifecycleScope.launch { repository.update { it.copy(enabled = true) } }
        }
        return START_STICKY
    }

    override fun onDestroy() {
        hideShield()
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

        val cameraDecision: Flow<CameraDecision> =
            combine(settingsFlow, device, pausedForPackage, runtime.appForegroundCount) { s, d, paused, appVisits ->
                when {
                    !s.enabled || !s.faceDetectionEnabled || paused != null || !d.screenOn -> CameraDecision.Off
                    s.batterySaver && d.powerSaveMode -> CameraDecision.PausedForBattery
                    !permissions.hasCameraPermission() -> CameraDecision.Unavailable
                    // Retry after the user opens the app, when Android allows camera use again.
                    else -> CameraDecision.Run(
                        intervalMs = if (s.batterySaver) SLOW_ANALYSIS_MS else FAST_ANALYSIS_MS,
                        retryKey = if (cameraTypeGranted) 0 else appVisits,
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
                        faceDetection.viewerReports(decision.intervalMs)
                    } else {
                        flowOf(ViewerReport(ViewerState.BLOCKED_IN_BACKGROUND))
                    }
            }
        }

        scope.launch {
            combine(
                settingsFlow, pausedForPackage, viewerReports, blurSupport.availability(), runtime.shieldTest,
            ) { s, paused, report, blurAvailable, testing ->
                RenderInput(s, paused, report, blurAvailable, testing)
            }.collect { render(it) }
        }
    }

    private fun render(input: RenderInput) {
        val settings = input.settings
        val pausedForPackage = input.pausedForPackage
        val viewer = input.report.state
        val canDraw = permissions.canDrawOverlays()
        val show = settings.enabled && pausedForPackage == null && canDraw
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
        if (shieldBlurs) showShield(PrivacyPolicy.blurRadiusDp(settings.blurStrength)) else hideShield()
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
            )
        }
        updateViewerAlert(
            visible && settings.multipleViewerProtection && viewer == ViewerState.MULTIPLE_VIEWERS,
            input.report.extraViewerSide,
        )
        updateStatus(statusContent(settings, pausedLabel, input.report, canDraw))
    }

    private fun showShield(radiusDp: Float) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.S) return
        try {
            (shieldWindow ?: BlurShieldWindow(this).also { shieldWindow = it }).show(radiusDp)
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
    ): StatusContent = when {
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
    )

    private sealed interface CameraDecision {
        data object Off : CameraDecision
        data object PausedForBattery : CameraDecision
        data object Unavailable : CameraDecision
        data class Run(val intervalMs: Long, val retryKey: Int) : CameraDecision
    }

    companion object {
        const val ACTION_DISABLE = "com.innovatyou.privacydisplay.action.DISABLE"
        const val ACTION_ENABLE = "com.innovatyou.privacydisplay.action.ENABLE"
        private const val TAG = "PrivacyOverlayService"

        /** Time between face detection runs. */
        private const val FAST_ANALYSIS_MS = 500L
        private const val SLOW_ANALYSIS_MS = 1_500L

        /** Time between foreground app checks when exclusions are set. */
        private const val FAST_POLL_MS = 1_000L
        private const val SLOW_POLL_MS = 2_000L

        private const val ALERT_MIN_INTERVAL_MS = 30_000L

        fun startIntent(context: Context) = Intent(context, PrivacyOverlayService::class.java)
    }
}
