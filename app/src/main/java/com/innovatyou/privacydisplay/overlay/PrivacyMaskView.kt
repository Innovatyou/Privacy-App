package com.innovatyou.privacydisplay.overlay

import android.annotation.SuppressLint
import android.content.Context
import android.graphics.Canvas
import android.view.View

/**
 * The view placed in the overlay window. It never receives touches (the window is
 * FLAG_NOT_TOUCHABLE) and is hidden from accessibility services so TalkBack reads the app below.
 *
 * @param windowOpacity the alpha of the window this view is drawn in. Opacities are divided by it
 *   so that the result on screen matches the requested value, up to the window's limit.
 */
@SuppressLint("ViewConstructor")
class PrivacyMaskView(context: Context, private val windowOpacity: Float) : View(context) {
    private val renderer = MaskRenderer()

    var params: MaskParams? = null
        set(value) {
            if (field != value) {
                field = value
                invalidate()
            }
        }

    init {
        importantForAccessibility = IMPORTANT_FOR_ACCESSIBILITY_NO_HIDE_DESCENDANTS
    }

    override fun onDraw(canvas: Canvas) {
        val p = params ?: return
        val spec = MaskSpec.compute(p, width.toFloat(), height.toFloat())
        renderer.draw(canvas, spec, 1f / windowOpacity.coerceAtLeast(0.01f))
    }
}
