package com.innovatyou.privacydisplay.util

import android.content.Context
import android.hardware.display.DisplayManager
import android.os.Handler
import android.os.Looper
import android.view.Display
import android.view.Surface
import dagger.hilt.android.qualifiers.ApplicationContext
import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.coroutines.channels.awaitClose
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.callbackFlow
import kotlinx.coroutines.flow.distinctUntilChanged

enum class DeviceOrientation { PORTRAIT, LANDSCAPE }

data class DisplayState(val rotation: Int, val orientation: DeviceOrientation)

/**
 * Tracks the rotation of the built-in display. Uses [DisplayManager] rather than configuration
 * changes so that 180° rotations (which keep the same orientation) are reported too.
 */
@Singleton
class OrientationManager @Inject constructor(
    @ApplicationContext private val context: Context,
) {
    private val displayManager = context.getSystemService(DisplayManager::class.java)

    fun current(): DisplayState {
        val display = displayManager?.getDisplay(Display.DEFAULT_DISPLAY)
            ?: return DisplayState(Surface.ROTATION_0, DeviceOrientation.PORTRAIT)
        val rotation = display.rotation
        val mode = display.mode
        return DisplayState(
            rotation,
            orientationFor(rotation, mode.physicalWidth, mode.physicalHeight),
        )
    }

    fun displayChanges(): Flow<DisplayState> = callbackFlow {
        val listener = object : DisplayManager.DisplayListener {
            override fun onDisplayChanged(displayId: Int) {
                if (displayId == Display.DEFAULT_DISPLAY) trySend(current())
            }

            override fun onDisplayAdded(displayId: Int) = Unit
            override fun onDisplayRemoved(displayId: Int) = Unit
        }
        trySend(current())
        displayManager?.registerDisplayListener(listener, Handler(Looper.getMainLooper()))
        awaitClose { displayManager?.unregisterDisplayListener(listener) }
    }.distinctUntilChanged()

    companion object {
        /** Orientation of a display given its rotation and its natural (unrotated) size. */
        fun orientationFor(rotation: Int, naturalWidth: Int, naturalHeight: Int): DeviceOrientation {
            val naturallyLandscape = naturalWidth > naturalHeight
            val sideways = rotation == Surface.ROTATION_90 || rotation == Surface.ROTATION_270
            return if (naturallyLandscape != sideways) DeviceOrientation.LANDSCAPE else DeviceOrientation.PORTRAIT
        }
    }
}
