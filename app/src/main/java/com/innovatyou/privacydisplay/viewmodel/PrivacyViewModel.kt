package com.innovatyou.privacydisplay.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.innovatyou.privacydisplay.data.InstalledApp
import com.innovatyou.privacydisplay.data.InstalledAppsRepository
import com.innovatyou.privacydisplay.data.MaskMode
import com.innovatyou.privacydisplay.data.PreferencesRepository
import com.innovatyou.privacydisplay.data.PrivacySettings
import com.innovatyou.privacydisplay.data.ThemeMode
import com.innovatyou.privacydisplay.owner.OwnerFaceStore
import com.innovatyou.privacydisplay.service.PrivacyController
import com.innovatyou.privacydisplay.service.PrivacyRuntime
import com.innovatyou.privacydisplay.service.PrivacyRuntimeState
import com.innovatyou.privacydisplay.util.PermissionManager
import com.innovatyou.privacydisplay.util.PermissionState
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import kotlinx.coroutines.channels.Channel
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.receiveAsFlow
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch

data class PrivacyUiState(
    val settings: PrivacySettings = PrivacySettings(),
    val runtime: PrivacyRuntimeState = PrivacyRuntimeState(),
    val permissions: PermissionState = PermissionState(),
    /** Highest mask opacity Android allows while keeping touches working. */
    val maxOverlayOpacity: Float = 1f,
    val loaded: Boolean = false,
    /** Wall-clock time (ms) until which the screen is shared, or null when not sharing. */
    val sharingUntil: Long? = null,
    /** The owner's face print is set up. */
    val ownerEnrolled: Boolean = false,
) {
    /** Owner protection is switched on and has a face print to compare with. */
    val ownerProtectionReady: Boolean get() = settings.ownerProtection && ownerEnrolled
}

/** Actions that weaken owner protection and therefore need the owner's fingerprint or PIN. */
enum class GatedAction {
    DISABLE_PRIVACY,
    DISABLE_FACE_DETECTION,
    DISABLE_OWNER_PROTECTION,
    DELETE_FACE,
    SET_UP_FACE_AGAIN,
    ADD_FACE_SAMPLES,
    START_SHARING,
}

/** One-off requests from the ViewModel that the UI must carry out. */
sealed interface PrivacyEvent {
    data object RequestOverlayPermission : PrivacyEvent
    data object RequestCameraPermission : PrivacyEvent
    data object RequestNotificationPermission : PrivacyEvent
    data object ServiceStartFailed : PrivacyEvent
    data object CameraPermissionDenied : PrivacyEvent

    /** Ask for the owner's fingerprint or PIN, then call [PrivacyViewModel.onAuthenticated]. */
    data class Authenticate(val action: GatedAction) : PrivacyEvent

    /** Open the face set-up screen; [add] adds samples instead of starting over. */
    data class OpenFaceSetup(val add: Boolean = false) : PrivacyEvent

    /** Owner protection needs a screen lock (PIN, pattern or password) first. */
    data object ScreenLockNeeded : PrivacyEvent
}

@HiltViewModel
class PrivacyViewModel @Inject constructor(
    private val repository: PreferencesRepository,
    private val controller: PrivacyController,
    private val permissionManager: PermissionManager,
    private val appsRepository: InstalledAppsRepository,
    runtime: PrivacyRuntime,
    private val ownerFaces: OwnerFaceStore,
) : ViewModel() {

    private val permissions = MutableStateFlow(permissionManager.snapshot())
    private val maxOverlayOpacity = permissionManager.maxOverlayOpacity()

    val uiState: StateFlow<PrivacyUiState> =
        combine(
            repository.settings, runtime.state, permissions, runtime.sharingUntil, ownerFaces.enrolled,
        ) { settings, runtimeState, perms, sharingUntil, enrolled ->
            PrivacyUiState(
                settings, runtimeState, perms, maxOverlayOpacity, loaded = true,
                sharingUntil = sharingUntil, ownerEnrolled = enrolled,
            )
        }.stateIn(
            viewModelScope,
            SharingStarted.WhileSubscribed(STOP_TIMEOUT_MS),
            PrivacyUiState(permissions = permissions.value, maxOverlayOpacity = maxOverlayOpacity),
        )

    private val _apps = MutableStateFlow<List<InstalledApp>?>(null)
    val apps: StateFlow<List<InstalledApp>?> = _apps.asStateFlow()

    private val _recommendedApps = MutableStateFlow<List<InstalledApp>>(emptyList())

    /** Installed apps that are known not to work under an overlay, such as Google Play Store. */
    val recommendedApps: StateFlow<List<InstalledApp>> = _recommendedApps.asStateFlow()

    private val _events = Channel<PrivacyEvent>(Channel.BUFFERED)
    val events: Flow<PrivacyEvent> = _events.receiveAsFlow()

    /** Re-reads permissions, e.g. when returning from a system settings screen. */
    fun refreshPermissions() {
        permissions.value = permissionManager.snapshot()
    }

    fun setPrivacyEnabled(enabled: Boolean) {
        if (!enabled && gate(GatedAction.DISABLE_PRIVACY)) return
        viewModelScope.launch {
            if (enabled && !permissionManager.canDrawOverlays()) {
                _events.send(PrivacyEvent.RequestOverlayPermission)
                return@launch
            }
            if (enabled && !permissionManager.hasNotificationPermission()) {
                _events.send(PrivacyEvent.RequestNotificationPermission)
            }
            if (!controller.setPrivacyEnabled(enabled)) _events.send(PrivacyEvent.ServiceStartFailed)
        }
    }

    fun setStrength(value: Float) = update { it.copy(strength = value) }
    fun setClearAreaWidth(value: Float) = update { it.copy(clearAreaWidth = value) }
    fun setClearAreaHeight(value: Float) = update { it.copy(clearAreaHeight = value) }
    fun setEdgeOpacity(value: Float) = update { it.copy(edgeOpacity = value) }
    fun setGradientWidth(value: Float) = update { it.copy(gradientWidth = value) }
    fun setMaskMode(mode: MaskMode) = update { it.copy(maskMode = mode) }
    fun setStrongestMaskOnMultipleViewers(enabled: Boolean) =
        update { it.copy(strongestMaskOnMultipleViewers = enabled) }
    fun setBatterySaver(enabled: Boolean) = update { it.copy(batterySaver = enabled) }
    fun setThemeMode(mode: ThemeMode) = update { it.copy(themeMode = mode) }
    fun setBlurOnExtraViewer(enabled: Boolean) = update { it.copy(blurOnExtraViewer = enabled) }
    fun setBlurWhenAway(enabled: Boolean) = update { it.copy(blurWhenAway = enabled) }
    /** Saves the blur strength and shows the shield live, so the user sees each step. */
    fun setBlurStrength(value: Float) {
        viewModelScope.launch {
            repository.update { it.copy(blurStrength = value) }
            if (repository.settings.first().enabled) controller.testShield()
        }
    }

    fun setShareMinutes(minutes: Int) = update { it.copy(shareMinutes = minutes) }

    /** Turns privacy off for the share time so another person can look at the screen too. */
    fun startSharing() {
        if (!gate(GatedAction.START_SHARING)) controller.startSharing()
    }

    fun stopSharing() = controller.stopSharing()

    /** Shows the viewer shield for a few seconds. Only visible while Privacy Mode is on. */
    fun testShield() = controller.testShield()

    fun setFaceDetection(enabled: Boolean) {
        if (!enabled && gate(GatedAction.DISABLE_FACE_DETECTION)) return
        if (enabled && !permissionManager.hasCameraPermission()) {
            viewModelScope.launch { _events.send(PrivacyEvent.RequestCameraPermission) }
            return
        }
        update {
            it.copy(
                faceDetectionEnabled = enabled,
                // Multiple-viewer protection depends on face detection.
                multipleViewerProtection = enabled && it.multipleViewerProtection,
                ownerProtection = enabled && it.ownerProtection,
            )
        }
    }

    /** Turns owner protection on (after a face is set up) or off (needs the owner). */
    fun setOwnerProtection(enabled: Boolean) {
        if (!enabled) {
            if (gate(GatedAction.DISABLE_OWNER_PROTECTION)) return
            update { it.copy(ownerProtection = false) }
            return
        }
        when {
            !permissionManager.hasScreenLock() -> send(PrivacyEvent.ScreenLockNeeded)
            !permissionManager.hasCameraPermission() -> send(PrivacyEvent.RequestCameraPermission)
            !ownerFaces.enrolled.value -> send(PrivacyEvent.OpenFaceSetup())
            else -> update { it.copy(ownerProtection = true, faceDetectionEnabled = true) }
        }
    }

    /** Opens face set-up; setting up again replaces the owner, so it needs the owner. */
    fun setUpFace() {
        when {
            !permissionManager.hasScreenLock() -> send(PrivacyEvent.ScreenLockNeeded)
            !permissionManager.hasCameraPermission() -> send(PrivacyEvent.RequestCameraPermission)
            !gate(GatedAction.SET_UP_FACE_AGAIN) -> send(PrivacyEvent.OpenFaceSetup())
        }
    }

    /** Adds samples to the face print, for example in the lighting used at night. Needs the owner. */
    fun addFaceSamples() {
        when {
            !permissionManager.hasCameraPermission() -> send(PrivacyEvent.RequestCameraPermission)
            !gate(GatedAction.ADD_FACE_SAMPLES) -> send(PrivacyEvent.OpenFaceSetup(add = true))
        }
    }

    fun setBlockWhenTooDark(enabled: Boolean) = update { it.copy(blockWhenTooDark = enabled) }

    fun setLowLightAssist(enabled: Boolean) = update { it.copy(lowLightAssist = enabled) }

    fun setDarkenWhenNobodyLooking(enabled: Boolean) = update { it.copy(darkenWhenNobodyLooking = enabled) }

    fun setPauseEffectsInDark(enabled: Boolean) = update { it.copy(pauseEffectsInDark = enabled) }

    /** Called when face set-up finished: protection is switched on. */
    fun onFaceSetUp() = update { it.copy(ownerProtection = true, faceDetectionEnabled = true) }

    fun deleteFace() {
        if (gate(GatedAction.DELETE_FACE)) return
        viewModelScope.launch {
            repository.update { it.copy(ownerProtection = false) }
            ownerFaces.delete()
        }
    }

    /** The owner confirmed with a fingerprint or PIN: carry out the action that needed it. */
    fun onAuthenticated(action: GatedAction) {
        controller.ownerAuthenticated()
        when (action) {
            GatedAction.DISABLE_PRIVACY -> viewModelScope.launch {
                if (!controller.setPrivacyEnabled(false)) _events.send(PrivacyEvent.ServiceStartFailed)
            }
            GatedAction.DISABLE_FACE_DETECTION -> update {
                it.copy(faceDetectionEnabled = false, multipleViewerProtection = false, ownerProtection = false)
            }
            GatedAction.DISABLE_OWNER_PROTECTION -> update { it.copy(ownerProtection = false) }
            GatedAction.DELETE_FACE -> viewModelScope.launch {
                repository.update { it.copy(ownerProtection = false) }
                ownerFaces.delete()
            }
            GatedAction.SET_UP_FACE_AGAIN -> send(PrivacyEvent.OpenFaceSetup())
            GatedAction.ADD_FACE_SAMPLES -> send(PrivacyEvent.OpenFaceSetup(add = true))
            GatedAction.START_SHARING -> controller.startSharing()
        }
    }

    /**
     * Returns true (and asks for the owner's fingerprint or PIN) when owner protection is on, so
     * the caller must not carry out the action yet.
     */
    private fun gate(action: GatedAction): Boolean {
        val protected = ownerFaces.enrolled.value && lastSettings?.ownerProtection == true
        if (protected) send(PrivacyEvent.Authenticate(action))
        return protected
    }

    private fun send(event: PrivacyEvent) {
        viewModelScope.launch { _events.send(event) }
    }

    @Volatile private var lastSettings: PrivacySettings? = null

    init {
        viewModelScope.launch { repository.settings.collect { lastSettings = it } }
    }

    fun onCameraPermissionResult(granted: Boolean) {
        refreshPermissions()
        if (granted) {
            update { it.copy(faceDetectionEnabled = true) }
        } else {
            viewModelScope.launch { _events.send(PrivacyEvent.CameraPermissionDenied) }
        }
    }

    fun setMultipleViewerProtection(enabled: Boolean) =
        update { it.copy(multipleViewerProtection = enabled && it.faceDetectionEnabled) }

    fun setAutoEnableOnUnlock(enabled: Boolean) {
        viewModelScope.launch {
            repository.update { it.copy(autoEnableOnUnlock = enabled) }
            // Standby needs the service running so it can hear unlock events.
            if (enabled) controller.syncService()
        }
    }

    fun toggleExcludedApp(packageName: String) = update {
        val apps = if (packageName in it.excludedApps) it.excludedApps - packageName else it.excludedApps + packageName
        it.copy(excludedApps = apps)
    }

    fun loadApps() {
        if (_apps.value != null) return
        viewModelScope.launch {
            _recommendedApps.value = appsRepository.recommendedApps()
            _apps.value = appsRepository.launchableApps()
        }
    }

    private fun update(transform: (PrivacySettings) -> PrivacySettings) {
        viewModelScope.launch { repository.update(transform) }
    }

    private companion object {
        const val STOP_TIMEOUT_MS = 5_000L
    }
}
