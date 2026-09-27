package com.innovatyou.privacyscreen

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapShader
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.Paint
import android.graphics.Shader
import android.view.View
import kotlin.math.roundToInt

/**
 * Full-screen, non-touchable view that draws the privacy filter:
 *  - a dark tint (content stays readable head-on but washes out at an angle and in reflections),
 *  - an optional fine pattern (like the slats of a physical privacy film) that breaks up the image,
 *  - an optional "spotlight" mode where only a horizontal reading band is visible.
 *
 * [maxOpacity] is the highest opacity the window may have while still letting touches through
 * (Android 12+ blocks touches under overlays that are more than 80% opaque).
 */
class PrivacyOverlayView(context: Context, private val maxOpacity: Float) : View(context) {

    var settings: PrivacySettings = PrivacySettings()
        set(value) {
            val patternChanged = value.pattern != field.pattern
            field = value
            if (patternChanged) patternPaint.shader = buildPatternShader(value.pattern)
            invalidate()
        }

    private val dimPaint = Paint()
    private val blackoutPaint = Paint().apply { color = Color.BLACK }
    private val edgePaint = Paint().apply {
        color = Color.argb(160, 90, 160, 255)
        strokeWidth = resources.displayMetrics.density * 2
    }
    private val patternPaint = Paint().apply { shader = buildPatternShader(settings.pattern) }

    override fun onDraw(canvas: Canvas) {
        val w = width.toFloat()
        val h = height.toFloat()
        val s = settings

        dimPaint.color = Color.argb(alphaFor(s.dimPercent), 0, 0, 0)
        patternPaint.alpha = alphaFor(s.patternPercent)

        if (!s.spotlight) {
            canvas.drawRect(0f, 0f, w, h, dimPaint)
            if (s.pattern != FilterPattern.NONE) canvas.drawRect(0f, 0f, w, h, patternPaint)
            return
        }

        val bandHeight = h * s.spotlightHeightPercent / 100f
        val top = (h * s.spotlightCenter - bandHeight / 2).coerceIn(0f, h - bandHeight)
        val bottom = top + bandHeight

        // Everything outside the reading band is blacked out as much as Android allows.
        canvas.drawRect(0f, 0f, w, top, blackoutPaint)
        canvas.drawRect(0f, bottom, w, h, blackoutPaint)

        canvas.drawRect(0f, top, w, bottom, dimPaint)
        if (s.pattern != FilterPattern.NONE) canvas.drawRect(0f, top, w, bottom, patternPaint)
        canvas.drawLine(0f, top, w, top, edgePaint)
        canvas.drawLine(0f, bottom, w, bottom, edgePaint)
    }

    /**
     * The window itself is drawn at [maxOpacity], so scale pixel alpha up to make the on-screen
     * result match the requested percentage (capped at [maxOpacity]).
     */
    private fun alphaFor(percent: Int): Int =
        (percent / 100f / maxOpacity * 255f).roundToInt().coerceIn(0, 255)

    private fun buildPatternShader(pattern: FilterPattern): Shader? {
        // Tiles are drawn at physical-pixel scale so the pattern is as fine as the display allows.
        val bitmap = when (pattern) {
            FilterPattern.NONE -> return null
            FilterPattern.VERTICAL_LINES -> tile(3, 1) { x, _ -> x == 0 }
            FilterPattern.GRID -> tile(3, 3) { x, y -> x == 0 || y == 0 }
            FilterPattern.DOTS -> tile(2, 2) { x, y -> (x + y) % 2 == 0 }
        }
        return BitmapShader(bitmap, Shader.TileMode.REPEAT, Shader.TileMode.REPEAT)
    }

    private inline fun tile(w: Int, h: Int, dark: (Int, Int) -> Boolean): Bitmap {
        val bitmap = Bitmap.createBitmap(w, h, Bitmap.Config.ARGB_8888)
        for (x in 0 until w) for (y in 0 until h) {
            bitmap.setPixel(x, y, if (dark(x, y)) Color.BLACK else Color.TRANSPARENT)
        }
        return bitmap
    }
}
