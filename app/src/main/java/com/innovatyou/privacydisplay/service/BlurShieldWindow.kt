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

    fun show(radiusDp: Float) {
        val radiusPx = (radiusDp * density).roundToInt()
        val current = view
        if (current == null) {
            layoutParams.blurBehindRadius = radiusPx
            OverlayWindows.applyScreenSize(windowManager, layoutParams)
            val shield = View(windowContext).apply {
                // A light veil makes it obvious that the shield is on.
                setBackgroundColor(Color.argb(VEIL_ALPHA, 0, 0, 0))
                importantForAccessibility = View.IMPORTANT_FOR_ACCESSIBILITY_NO_HIDE_DESCENDANTS
            }
            windowManager.addView(shield, layoutParams)
            view = shield
        } else if (layoutParams.blurBehindRadius != radiusPx) {
            layoutParams.blurBehindRadius = radiusPx
            windowManager.updateViewLayout(current, layoutParams)
        }
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
        const val VEIL_ALPHA = 40
    }
}
