package com.innovatyou.privacydisplay.service

import android.content.Context
import android.graphics.Color
import android.os.Build
import android.view.View
import android.view.WindowManager
import androidx.annotation.RequiresApi
import com.innovatyou.privacydisplay.util.AndroidPermissionManager
import kotlin.math.roundToInt

/**
 * The viewer shield: a transparent full-screen window with FLAG_BLUR_BEHIND, which makes the
 * system compositor blur everything behind it (Android 12+ cross-window blur). The window itself
 * is not touchable, so the phone stays usable while the screen is blurred.
 *
 * Blur-behind always blurs the whole screen behind the window; Android offers no way for another
 * app to blur only part of the screen or only for certain viewing angles.
 */
@RequiresApi(Build.VERSION_CODES.S)
class BlurShieldWindow(serviceContext: Context) {

    private val windowContext = OverlayWindows.windowContext(serviceContext)
    private val windowManager = windowContext.getSystemService(WindowManager::class.java)
    private val density = serviceContext.resources.displayMetrics.density
    private val layoutParams = OverlayWindows.fullScreenParams(
        title = "PrivacyDisplayShield",
        alpha = AndroidPermissionManager.maxOverlayOpacity(serviceContext),
        extraFlags = WindowManager.LayoutParams.FLAG_BLUR_BEHIND,
    )
    private var view: View? = null

    val isShowing: Boolean get() = view != null

    /**
     * @param radiusDp blur radius.
     * @param veilAlpha opacity (0–1) of the frosted veil drawn on top of the blur.
     */
    fun show(radiusDp: Float, veilAlpha: Float) {
        val radiusPx = (radiusDp * density).roundToInt()
        val veil = Color.argb((veilAlpha.coerceIn(0f, 1f) * 255).roundToInt(), VEIL_GREY, VEIL_GREY, VEIL_GREY)
        val current = view
        if (current != null && layoutParams.blurBehindRadius != radiusPx) {
            // Some devices ignore a blur radius changed on a window that is already showing, so
            // the window is re-added to make the new strength take effect.
            hide()
        }
        val shield = view ?: View(windowContext).also {
            layoutParams.blurBehindRadius = radiusPx
            OverlayWindows.applyScreenSize(windowManager, layoutParams)
            it.importantForAccessibility = View.IMPORTANT_FOR_ACCESSIBILITY_NO_HIDE_DESCENDANTS
            windowManager.addView(it, layoutParams)
            view = it
        }
        shield.setBackgroundColor(veil)
    }

    fun hide() {
        view?.let { windowManager.removeView(it) }
        view = null
    }

    fun onDisplayChanged() {
        val current = view ?: return
        if (OverlayWindows.applyScreenSize(windowManager, layoutParams)) {
            windowManager.updateViewLayout(current, layoutParams)
        }
    }

    private companion object {
        const val VEIL_GREY = 40
    }
}
