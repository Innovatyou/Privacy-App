package com.innovatyou.privacydisplay.overlay

import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.Paint
import android.graphics.RectF
import kotlin.math.ceil
import kotlin.math.roundToInt

/**
 * Draws a [MaskSpec] onto a [Canvas].
 *
 * Hard-edged masks are drawn as rectangles. Soft masks are rendered once into a small bitmap
 * (1/[DOWNSAMPLE] of the screen size, which is plenty for smooth gradients) and scaled up with
 * bilinear filtering. The bitmap is only rebuilt when the mask changes, not on every frame.
 */
class MaskRenderer {
    private val solidPaint = Paint()
    private val bitmapPaint = Paint(Paint.FILTER_BITMAP_FLAG)
    private val destination = RectF()
    private var cachedSpec: MaskSpec? = null
    private var cachedAlphaScale = 0f
    private var cachedBitmap: Bitmap? = null

    /**
     * @param alphaScale multiplier applied to every opacity, used to compensate for a window alpha
     *   below 1 so the final on-screen opacity matches the spec.
     */
    fun draw(canvas: Canvas, spec: MaskSpec, alphaScale: Float) {
        if (spec.isEmpty || spec.width <= 0f || spec.height <= 0f) return
        if (spec.hardEdge) drawHard(canvas, spec, alphaScale) else drawSoft(canvas, spec, alphaScale)
    }

    private fun drawHard(canvas: Canvas, spec: MaskSpec, alphaScale: Float) {
        solidPaint.color = black(spec.outsideAlpha, alphaScale)
        canvas.drawRect(0f, 0f, spec.width, spec.clearTop, solidPaint)
        canvas.drawRect(0f, spec.clearBottom, spec.width, spec.height, solidPaint)
        canvas.drawRect(0f, spec.clearTop, spec.clearLeft, spec.clearBottom, solidPaint)
        canvas.drawRect(spec.clearRight, spec.clearTop, spec.width, spec.clearBottom, solidPaint)
        if (spec.centerAlpha > 0f) {
            solidPaint.color = black(spec.centerAlpha, alphaScale)
            canvas.drawRect(spec.clearLeft, spec.clearTop, spec.clearRight, spec.clearBottom, solidPaint)
        }
    }

    private fun drawSoft(canvas: Canvas, spec: MaskSpec, alphaScale: Float) {
        val bitmap = softBitmap(spec, alphaScale)
        destination.set(0f, 0f, spec.width, spec.height)
        canvas.drawBitmap(bitmap, null, destination, bitmapPaint)
    }

    private fun softBitmap(spec: MaskSpec, alphaScale: Float): Bitmap {
        val cached = cachedBitmap
        if (cached != null && spec == cachedSpec && alphaScale == cachedAlphaScale) return cached

        val w = ceil(spec.width / DOWNSAMPLE).toInt().coerceAtLeast(1)
        val h = ceil(spec.height / DOWNSAMPLE).toInt().coerceAtLeast(1)
        val pixels = IntArray(w * h)
        val stepX = spec.width / w
        val stepY = spec.height / h
        for (y in 0 until h) {
            val py = (y + 0.5f) * stepY
            val row = y * w
            for (x in 0 until w) {
                val alpha = spec.alphaAt((x + 0.5f) * stepX, py)
                pixels[row + x] = if (spec.frosted) {
                    frost(alpha * grain(x, y), alphaScale)
                } else {
                    black(alpha, alphaScale)
                }
            }
        }
        // A new bitmap each time: the previous one may still be referenced by a pending frame.
        val bitmap = Bitmap.createBitmap(pixels, w, h, Bitmap.Config.ARGB_8888)
        cachedBitmap = bitmap
        cachedSpec = spec
        cachedAlphaScale = alphaScale
        return bitmap
    }

    private fun black(alpha: Float, scale: Float): Int =
        Color.argb((alpha * scale * 255f).roundToInt().coerceIn(0, 255), 0, 0, 0)

    private fun frost(alpha: Float, scale: Float): Int =
        Color.argb((alpha * scale * 255f).roundToInt().coerceIn(0, 255), FROST_R, FROST_G, FROST_B)

    private companion object {
        const val DOWNSAMPLE = 4f
        const val FROST_R = 176
        const val FROST_G = 184
        const val FROST_B = 196

        /** Deterministic per-cell grain in 0.6–1.4, so the frost looks textured and stable. */
        fun grain(x: Int, y: Int): Float {
            var h = x * 374761393 + y * 668265263
            h = (h xor (h ushr 13)) * 1274126177
            h = h xor (h ushr 16)
            return 0.6f + 0.8f * ((h and 0xFFFF) / 65535f)
        }
    }
}
