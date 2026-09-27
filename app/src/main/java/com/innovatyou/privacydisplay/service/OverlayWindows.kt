package com.innovatyou.privacydisplay.service

import android.content.Context
import android.graphics.PixelFormat
import android.graphics.Rect
import android.hardware.display.DisplayManager
import android.os.Build
import android.util.DisplayMetrics
import android.view.Display
import android.view.Gravity
import android.view.WindowManager

/**
 * Shared setup for the full-screen overlay windows.
 *
 * - TYPE_APPLICATION_OVERLAY: the only overlay type available to third-party apps (API 26+).
 * - FLAG_NOT_TOUCHABLE + FLAG_NOT_FOCUSABLE: touches and key events go to the app underneath.
 * - FLAG_LAYOUT_IN_SCREEN + FLAG_LAYOUT_NO_LIMITS + cutout mode ALWAYS + no inset fitting: cover
 *   the whole display, including the status bar, navigation bar and display cutout.
 * - alpha capped at InputManager.maximumObscuringOpacityForTouch (0.8): on Android 12+ touches are
 *   blocked under untrusted overlays that are more opaque than this.
 * - On Android 11+ windows are added through a window context (Context.createWindowContext), the
 *   supported way to show windows from a Service; older versions use the service context.
 */
internal object OverlayWindows {

    fun windowContext(serviceContext: Context): Context =
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            val display = serviceContext.getSystemService(DisplayManager::class.java)
                .getDisplay(Display.DEFAULT_DISPLAY)
            serviceContext.createDisplayContext(display)
                .createWindowContext(WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY, null)
        } else {
            serviceContext
        }

    fun fullScreenParams(title: String, alpha: Float, extraFlags: Int = 0) = WindowManager.LayoutParams(
        WindowManager.LayoutParams.MATCH_PARENT,
        WindowManager.LayoutParams.MATCH_PARENT,
        WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY,
        WindowManager.LayoutParams.FLAG_NOT_TOUCHABLE or
            WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE or
            WindowManager.LayoutParams.FLAG_LAYOUT_IN_SCREEN or
            WindowManager.LayoutParams.FLAG_LAYOUT_NO_LIMITS or
            WindowManager.LayoutParams.FLAG_HARDWARE_ACCELERATED or
            extraFlags,
        PixelFormat.TRANSLUCENT,
    ).apply {
        this.title = title
        gravity = Gravity.TOP or Gravity.START
        this.alpha = alpha
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            layoutInDisplayCutoutMode = WindowManager.LayoutParams.LAYOUT_IN_DISPLAY_CUTOUT_MODE_ALWAYS
            fitInsetsTypes = 0
        } else if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            layoutInDisplayCutoutMode = WindowManager.LayoutParams.LAYOUT_IN_DISPLAY_CUTOUT_MODE_SHORT_EDGES
        }
    }

    /** Sizes [params] to the whole display. Returns true if the size changed. */
    fun applyScreenSize(windowManager: WindowManager, params: WindowManager.LayoutParams): Boolean {
        val bounds = screenBounds(windowManager)
        val changed = params.width != bounds.width() || params.height != bounds.height()
        params.width = bounds.width()
        params.height = bounds.height()
        params.x = 0
        params.y = 0
        return changed
    }

    private fun screenBounds(windowManager: WindowManager): Rect =
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            windowManager.maximumWindowMetrics.bounds
        } else {
            val metrics = DisplayMetrics()
            @Suppress("DEPRECATION")
            windowManager.defaultDisplay.getRealMetrics(metrics)
            Rect(0, 0, metrics.widthPixels, metrics.heightPixels)
        }
}
