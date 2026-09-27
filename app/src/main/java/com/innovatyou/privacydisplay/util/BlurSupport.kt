package com.innovatyou.privacydisplay.util

import android.content.Context
import android.os.Build
import android.view.WindowManager
import androidx.core.content.ContextCompat
import dagger.hilt.android.qualifiers.ApplicationContext
import java.util.function.Consumer
import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.coroutines.channels.awaitClose
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.callbackFlow
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.flowOf

/**
 * Whether the system can blur behind windows. Needs Android 12+ and a device/GPU that supports
 * cross-window blur; the system also turns it off temporarily, for example in Battery Saver.
 */
@Singleton
class BlurSupport @Inject constructor(
    @ApplicationContext private val context: Context,
) {
    fun isAvailable(): Boolean =
        Build.VERSION.SDK_INT >= Build.VERSION_CODES.S &&
            context.getSystemService(WindowManager::class.java)?.isCrossWindowBlurEnabled == true

    fun availability(): Flow<Boolean> {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.S) return flowOf(false)
        val windowManager = context.getSystemService(WindowManager::class.java) ?: return flowOf(false)
        return callbackFlow {
            val listener = Consumer<Boolean> { enabled -> trySend(enabled) }
            trySend(windowManager.isCrossWindowBlurEnabled)
            windowManager.addCrossWindowBlurEnabledListener(ContextCompat.getMainExecutor(context), listener)
            awaitClose { windowManager.removeCrossWindowBlurEnabledListener(listener) }
        }.distinctUntilChanged()
    }
}
