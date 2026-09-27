package com.innovatyou.privacydisplay.service

import android.content.Context
import android.view.WindowManager
import com.innovatyou.privacydisplay.overlay.MaskParams
import com.innovatyou.privacydisplay.overlay.PrivacyMaskView
import com.innovatyou.privacydisplay.util.AndroidPermissionManager

/** Owns the full-screen window that draws the privacy mask. See [OverlayWindows] for the setup. */
class PrivacyOverlayWindow(serviceContext: Context) {

    private val windowContext = OverlayWindows.windowContext(serviceContext)
    private val windowManager = windowContext.getSystemService(WindowManager::class.java)
    private val windowOpacity = AndroidPermissionManager.maxOverlayOpacity(serviceContext)
    private val layoutParams = OverlayWindows.fullScreenParams("PrivacyDisplayMask", windowOpacity)
    private var view: PrivacyMaskView? = null

    val isShowing: Boolean get() = view != null

    fun show(params: MaskParams) {
        val current = view ?: PrivacyMaskView(windowContext, windowOpacity).also {
            OverlayWindows.applyScreenSize(windowManager, layoutParams)
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
        if (OverlayWindows.applyScreenSize(windowManager, layoutParams)) {
            windowManager.updateViewLayout(current, layoutParams)
        }
    }
}
