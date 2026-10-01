package com.innovatyou.privacydisplay.service

import android.annotation.SuppressLint
import android.content.Context
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.LinearGradient
import android.graphics.Paint
import android.graphics.Shader
import android.view.View
import android.view.WindowManager
import com.innovatyou.privacydisplay.util.AndroidPermissionManager

/**
 * Low-light assist: a soft white glow along the screen edges that lights the owner's face for
 * the front camera in dim rooms, like the front "flash" of selfie cameras. Not touchable.
 */
class LightRingWindow(serviceContext: Context) {

    private val windowContext = OverlayWindows.windowContext(serviceContext)
    private val windowManager = windowContext.getSystemService(WindowManager::class.java)
    private val layoutParams = OverlayWindows.fullScreenParams(
        title = "PrivacyDisplayLightRing",
        alpha = AndroidPermissionManager.maxOverlayOpacity(serviceContext),
    )
    private val glowWidthPx = GLOW_DP * serviceContext.resources.displayMetrics.density
    private var view: View? = null

    val isShowing: Boolean get() = view != null

    fun show() {
        if (view != null) return
        OverlayWindows.applyScreenSize(windowManager, layoutParams)
        val ring = GlowView(windowContext, glowWidthPx).apply {
            importantForAccessibility = View.IMPORTANT_FOR_ACCESSIBILITY_NO_HIDE_DESCENDANTS
        }
        windowManager.addView(ring, layoutParams)
        view = ring
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

    @SuppressLint("ViewConstructor")
    private class GlowView(context: Context, private val glow: Float) : View(context) {
        private val paint = Paint()

        override fun onDraw(canvas: Canvas) {
            val w = width.toFloat()
            val h = height.toFloat()
            edge(canvas, 0f, 0f, w, glow, 0f, 0f, 0f, glow) // top
            edge(canvas, 0f, h - glow, w, h, 0f, h, 0f, h - glow) // bottom
            edge(canvas, 0f, 0f, glow, h, 0f, 0f, glow, 0f) // left
            edge(canvas, w - glow, 0f, w, h, w, 0f, w - glow, 0f) // right
        }

        private fun edge(
            canvas: Canvas,
            left: Float, top: Float, right: Float, bottom: Float,
            x0: Float, y0: Float, x1: Float, y1: Float,
        ) {
            paint.shader = LinearGradient(x0, y0, x1, y1, Color.WHITE, Color.TRANSPARENT, Shader.TileMode.CLAMP)
            canvas.drawRect(left, top, right, bottom, paint)
        }
    }

    private companion object {
        const val GLOW_DP = 56f
    }
}
