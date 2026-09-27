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
import com.innovatyou.privacydisplay.overlay.MaskParams
import com.innovatyou.privacydisplay.overlay.PrivacyMaskView
import com.innovatyou.privacydisplay.util.AndroidPermissionManager

/**
 * Owns the full-screen overlay window.
 *
 * Window setup:
 * - TYPE_APPLICATION_OVERLAY: the only overlay type available to third-party apps (API 26+).
 * - FLAG_NOT_TOUCHABLE + FLAG_NOT_FOCUSABLE: touches and key events go to the app underneath.
 * - FLAG_LAYOUT_IN_SCREEN + FLAG_LAYOUT_NO_LIMITS + cutout mode ALWAYS + no inset fitting: cover
 *   the whole display, including the status bar, navigation bar and display cutout.
 * - alpha capped at InputManager.maximumObscuringOpacityForTouch (0.8): on Android 12+ touches are
 *   blocked under untrusted overlays that are more opaque than this.
 * - On Android 11+ the window is added through a window context (Context.createWindowContext), the
 *   supported way to show windows from a Service; older versions use the service context.
 */
class PrivacyOverlayWindow(serviceContext: Context) {

    private val windowContext: Context = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
        val display = serviceContext.getSystemService(DisplayManager::class.java)
            .getDisplay(Display.DEFAULT_DISPLAY)
        serviceContext.createDisplayContext(display)
            .createWindowContext(WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY, null)
    } else {
        serviceContext
    }
    private val windowManager = windowContext.getSystemService(WindowManager::class.java)
    private val windowOpacity = AndroidPermissionManager.maxOverlayOpacity(serviceContext)
    private val layoutParams = createLayoutParams()
    private var view: PrivacyMaskView? = null

    val isShowing: Boolean get() = view != null

    fun show(params: MaskParams) {
        val current = view ?: PrivacyMaskView(windowContext, windowOpacity).also {
            applyScreenSize()
            windowManager.addView(it, layoutParams)
            view = it
        }
        current.params = params
    }

    fun hide() {
        view?.let { windowManager.removeView(it) }
        view = null
    }

    /** Resize after a rotation, fold or resolution change. */
    fun onDisplayChanged() {
        val current = view ?: return
        val before = layoutParams.width to layoutParams.height
        applyScreenSize()
        if (before != layoutParams.width to layoutParams.height) {
            windowManager.updateViewLayout(current, layoutParams)
        }
    }

    private fun createLayoutParams() = WindowManager.LayoutParams(
        WindowManager.LayoutParams.MATCH_PARENT,
        WindowManager.LayoutParams.MATCH_PARENT,
        WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY,
        WindowManager.LayoutParams.FLAG_NOT_TOUCHABLE or
            WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE or
            WindowManager.LayoutParams.FLAG_LAYOUT_IN_SCREEN or
            WindowManager.LayoutParams.FLAG_LAYOUT_NO_LIMITS or
            WindowManager.LayoutParams.FLAG_HARDWARE_ACCELERATED,
        PixelFormat.TRANSLUCENT,
    ).apply {
        title = "PrivacyDisplayMask"
        gravity = Gravity.TOP or Gravity.START
        alpha = windowOpacity
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            layoutInDisplayCutoutMode = WindowManager.LayoutParams.LAYOUT_IN_DISPLAY_CUTOUT_MODE_ALWAYS
            fitInsetsTypes = 0
        } else if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            layoutInDisplayCutoutMode = WindowManager.LayoutParams.LAYOUT_IN_DISPLAY_CUTOUT_MODE_SHORT_EDGES
        }
    }

    private fun applyScreenSize() {
        val bounds = screenBounds()
        layoutParams.width = bounds.width()
        layoutParams.height = bounds.height()
        layoutParams.x = 0
        layoutParams.y = 0
    }

    private fun screenBounds(): Rect =
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            windowManager.maximumWindowMetrics.bounds
        } else {
            val metrics = DisplayMetrics()
            @Suppress("DEPRECATION")
            windowManager.defaultDisplay.getRealMetrics(metrics)
            Rect(0, 0, metrics.widthPixels, metrics.heightPixels)
        }
}
