package com.innovatyou.privacydisplay.service

import com.innovatyou.privacydisplay.camera.ViewerState
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
)

@Singleton
class PrivacyRuntime @Inject constructor() {
    private val _state = MutableStateFlow(PrivacyRuntimeState())
    val state: StateFlow<PrivacyRuntimeState> = _state.asStateFlow()

    private val _appForegroundCount = MutableStateFlow(0)

    /** Increments each time the app UI comes to the foreground. */
    val appForegroundCount: StateFlow<Int> = _appForegroundCount.asStateFlow()

    fun update(transform: (PrivacyRuntimeState) -> PrivacyRuntimeState) = _state.update(transform)

    fun onAppForeground() = _appForegroundCount.update { it + 1 }
}
