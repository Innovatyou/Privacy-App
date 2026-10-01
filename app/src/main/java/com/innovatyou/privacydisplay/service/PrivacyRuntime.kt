package com.innovatyou.privacydisplay.service

import com.innovatyou.privacydisplay.camera.ViewerSide
import com.innovatyou.privacydisplay.camera.ViewerState
import com.innovatyou.privacydisplay.overlay.ShieldReason
import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update

/** Live state published by the overlay service for the UI. Not persisted. */
data class PrivacyRuntimeState(
    val serviceRunning: Boolean = false,
    val overlayVisible: Boolean = false,
    /** Label of the excluded app that is currently pausing the mask. */
    val pausedForApp: String? = null,
    val viewerState: ViewerState = ViewerState.OFF,
    val boosted: Boolean = false,
    /** Side of the additional viewer, when one is detected. */
    val extraViewerSide: ViewerSide? = null,
    /** Why the viewer shield is on, or null when it is off. */
    val shield: ShieldReason? = null,
    /** True when the shield blurs (system blur available) rather than darkens. */
    val shieldBlurs: Boolean = false,
)

@Singleton
class PrivacyRuntime @Inject constructor() {
    private val _state = MutableStateFlow(PrivacyRuntimeState())
    val state: StateFlow<PrivacyRuntimeState> = _state.asStateFlow()

    private val _appForegroundCount = MutableStateFlow(0)

    /** Increments each time the app UI comes to the foreground. */
    val appForegroundCount: StateFlow<Int> = _appForegroundCount.asStateFlow()

    private val _shieldTest = MutableStateFlow(false)

    /** True while the user previews the viewer shield from settings. */
    val shieldTest: StateFlow<Boolean> = _shieldTest.asStateFlow()

    fun update(transform: (PrivacyRuntimeState) -> PrivacyRuntimeState) = _state.update(transform)

    fun setShieldTest(active: Boolean) {
        _shieldTest.value = active
    }

    private val _sharingUntil = MutableStateFlow<Long?>(null)

    /** Wall-clock time (ms) until which the screen is shared and privacy is off, or null. */
    val sharingUntil: StateFlow<Long?> = _sharingUntil.asStateFlow()

    fun setSharingUntil(epochMs: Long?) {
        _sharingUntil.value = epochMs
    }

    private val _locked = MutableStateFlow(false)

    /** True while the phone is blocked because a face other than the owner's was seen. */
    val locked: StateFlow<Boolean> = _locked.asStateFlow()

    fun setLocked(locked: Boolean) {
        _locked.value = locked
    }

    private val _ownerTrustedUntil = MutableStateFlow<Long?>(null)

    /**
     * After the owner unlocks with a fingerprint or PIN, strangers are ignored until this time
     * (elapsed realtime) or until the screen turns off.
     */
    val ownerTrustedUntil: StateFlow<Long?> = _ownerTrustedUntil.asStateFlow()

    fun setOwnerTrustedUntil(elapsedMs: Long?) {
        _ownerTrustedUntil.value = elapsedMs
    }

    private val _cameraBusy = MutableStateFlow(false)

    /** True while the app's own screens use the camera (face setup), so the service lets go of it. */
    val cameraBusy: StateFlow<Boolean> = _cameraBusy.asStateFlow()

    fun setCameraBusy(busy: Boolean) {
        _cameraBusy.value = busy
    }

    private val _unlockScreenOpen = MutableStateFlow(false)

    /** True while the fingerprint/PIN screen is open, so the block screen does not cover it. */
    val unlockScreenOpen: StateFlow<Boolean> = _unlockScreenOpen.asStateFlow()

    fun setUnlockScreenOpen(open: Boolean) {
        _unlockScreenOpen.value = open
    }

    fun onAppForeground() = _appForegroundCount.update { it + 1 }
}
