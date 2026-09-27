package com.innovatyou.privacydisplay.util

import android.app.usage.UsageEvents
import android.app.usage.UsageStatsManager
import android.content.Context
import android.os.Build
import dagger.hilt.android.qualifiers.ApplicationContext
import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.flow
import kotlinx.coroutines.flow.flowOn

/**
 * Reports the package of the app in front, using Usage access. Android offers third-party apps no
 * callback for this, so it polls; it only runs while exclusions are configured and the screen is on.
 */
@Singleton
class ForegroundAppMonitor @Inject constructor(
    @ApplicationContext private val context: Context,
) {
    fun foregroundPackages(intervalMs: Long): Flow<String?> = flow {
        val usm = context.getSystemService(UsageStatsManager::class.java)
        if (usm == null) {
            emit(null)
            return@flow
        }
        val event = UsageEvents.Event()
        var current: String? = null
        var since = System.currentTimeMillis() - INITIAL_LOOKBACK_MS
        while (true) {
            val now = System.currentTimeMillis()
            val events = usm.queryEvents(since, now)
            while (events.hasNextEvent()) {
                events.getNextEvent(event)
                if (event.eventType == RESUMED_EVENT) current = event.packageName
            }
            since = now
            emit(current)
            delay(intervalMs)
        }
    }.distinctUntilChanged().flowOn(Dispatchers.IO)

    private companion object {
        const val INITIAL_LOOKBACK_MS = 60 * 60 * 1000L

        val RESUMED_EVENT = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            UsageEvents.Event.ACTIVITY_RESUMED
        } else {
            @Suppress("DEPRECATION")
            UsageEvents.Event.MOVE_TO_FOREGROUND
        }
    }
}
