package com.innovatyou.privacydisplay.service

import android.content.Context
import android.util.Log
import androidx.core.content.ContextCompat
import com.innovatyou.privacydisplay.data.PreferencesRepository
import com.innovatyou.privacydisplay.di.ApplicationScope
import dagger.hilt.android.qualifiers.ApplicationContext
import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch

/** Turns Privacy Mode on and off and keeps the overlay service running when it is needed. */
interface PrivacyController {
    /**
     * Saves the new state and starts the service if needed.
     * @return false if Android refused to start the service from the background.
     */
    suspend fun setPrivacyEnabled(enabled: Boolean): Boolean

    /** Starts the service if the current settings need it. Same return value as above. */
    suspend fun syncService(): Boolean

    /** Called when the app UI is visible, which is when Android allows camera use to start. */
    fun onAppForeground()

    /** Shows the viewer shield for a few seconds so the user can see what it looks like. */
    fun testShield()
}

@Singleton
class ServicePrivacyController @Inject constructor(
    @ApplicationContext private val context: Context,
    private val repository: PreferencesRepository,
    private val runtime: PrivacyRuntime,
    @ApplicationScope private val scope: CoroutineScope,
) : PrivacyController {

    override suspend fun setPrivacyEnabled(enabled: Boolean): Boolean {
        repository.update { it.copy(enabled = enabled) }
        return syncService()
    }

    override suspend fun syncService(): Boolean {
        // A running service follows settings changes by itself; it stops itself when not needed.
        if (runtime.state.value.serviceRunning) return true
        if (!repository.settings.first().needsService) return true
        return try {
            ContextCompat.startForegroundService(context, PrivacyOverlayService.startIntent(context))
            true
        } catch (e: IllegalStateException) {
            // ForegroundServiceStartNotAllowedException on Android 12+ when in the background.
            Log.w(TAG, "Not allowed to start the privacy service from the background", e)
            false
        }
    }

    override fun onAppForeground() {
        runtime.onAppForeground()
        scope.launch { syncService() }
    }

    override fun testShield() {
        shieldTestJob?.cancel()
        runtime.setShieldTest(true)
        shieldTestJob = scope.launch {
            delay(SHIELD_TEST_MS)
            runtime.setShieldTest(false)
        }
    }

    private var shieldTestJob: Job? = null

    private companion object {
        const val TAG = "PrivacyController"
        const val SHIELD_TEST_MS = 5_000L
    }
}
