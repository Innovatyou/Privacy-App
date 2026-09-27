package com.innovatyou.privacydisplay.overlay

import com.innovatyou.privacydisplay.data.MaskMode
import com.innovatyou.privacydisplay.data.PrivacySettings
import kotlin.math.max
import kotlin.math.min
import kotlin.math.sqrt

enum class MaskShape { RECTANGLE, ELLIPSE }

/**
 * Pixel geometry of the mask for a given screen size. Pure Kotlin so that it can be unit tested
 * and shared by the overlay view and the in-app preview.
 */
data class MaskSpec(
    val width: Float,
    val height: Float,
    val clearLeft: Float,
    val clearTop: Float,
    val clearRight: Float,
    val clearBottom: Float,
    /** Opacity (0–1) of the mask outside the clear area. */
    val outsideAlpha: Float,
    /** Opacity (0–1) of the mask inside the clear area. */
    val centerAlpha: Float,
    /** Width of the soft transition, in pixels. */
    val featherPx: Float,
    val shape: MaskShape,
    val hardEdge: Boolean,
) {
    val isEmpty: Boolean get() = outsideAlpha <= 0f && centerAlpha <= 0f

    /** Mask opacity (0–1) at the given pixel. */
    fun alphaAt(x: Float, y: Float): Float = when (shape) {
        MaskShape.RECTANGLE -> {
            val dx = maxOf(clearLeft - x, 0f, x - clearRight)
            val dy = maxOf(clearTop - y, 0f, y - clearBottom)
            val distance = sqrt(dx * dx + dy * dy)
            when {
                distance <= 0f -> centerAlpha
                hardEdge || featherPx <= 0f -> outsideAlpha
                else -> lerp(centerAlpha, outsideAlpha, smoothstep(0f, featherPx, distance))
            }
        }
        MaskShape.ELLIPSE -> {
            val rx = max((clearRight - clearLeft) / 2f, 1f)
            val ry = max((clearBottom - clearTop) / 2f, 1f)
            val nx = (x - (clearLeft + clearRight) / 2f) / rx
            val ny = (y - (clearTop + clearBottom) / 2f) / ry
            val r = sqrt(nx * nx + ny * ny)
            // Fully opaque by the nearest screen edge.
            val full = max(min(width / 2f / rx, height / 2f / ry), 1.05f)
            if (r <= 1f) centerAlpha else lerp(centerAlpha, outsideAlpha, smoothstep(1f, full, r))
        }
    }

    companion object {
        /** The narrow window is this fraction of the configured clear area. */
        const val NARROW_WINDOW_SCALE = 0.6f

        /** In Custom mode, the clear area is dimmed by this fraction of the privacy strength. */
        const val CUSTOM_CENTER_FACTOR = 0.5f

        fun compute(params: MaskParams, width: Float, height: Float): MaskSpec {
            // Orientation awareness: keep the clear area the same physical shape when the phone
            // rotates, so the settings (made in portrait) still fit in landscape.
            val landscape = width > height
            val widthFraction = if (landscape) params.clearAreaHeight else params.clearAreaWidth
            val heightFraction = if (landscape) params.clearAreaWidth else params.clearAreaHeight
            val scale = if (params.mode == MaskMode.NARROW_WINDOW) NARROW_WINDOW_SCALE else 1f
            val minFraction = PrivacySettings.MIN_CLEAR_AREA
            val clearWidth = width * (widthFraction * scale).coerceIn(minFraction, 1f)
            val clearHeight = height * (heightFraction * scale).coerceIn(minFraction, 1f)
            val left = (width - clearWidth) / 2f
            val top = (height - clearHeight) / 2f

            val strength = params.strength.coerceIn(0f, 1f)
            val edge = params.edgeOpacity.coerceIn(0f, 1f)
            val feather = params.gradientWidth.coerceIn(0f, PrivacySettings.MAX_GRADIENT_WIDTH) * min(width, height)

            fun spec(outside: Float, center: Float, featherPx: Float, shape: MaskShape, hard: Boolean) =
                MaskSpec(
                    width, height, left, top, left + clearWidth, top + clearHeight,
                    outside, center, featherPx, shape, hard,
                )

            return when (params.mode) {
                MaskMode.BLACK, MaskMode.NARROW_WINDOW ->
                    spec(strength, 0f, 0f, MaskShape.RECTANGLE, hard = true)
                MaskMode.DARK_EDGES ->
                    spec(strength * edge, 0f, feather, MaskShape.RECTANGLE, hard = false)
                MaskMode.GRADIENT ->
                    spec(strength * edge, 0f, feather, MaskShape.ELLIPSE, hard = false)
                MaskMode.CUSTOM -> {
                    val center = strength * CUSTOM_CENTER_FACTOR
                    spec(max(edge, center), center, feather, MaskShape.RECTANGLE, hard = false)
                }
            }
        }

        internal fun smoothstep(edge0: Float, edge1: Float, x: Float): Float {
            if (edge1 <= edge0) return if (x < edge0) 0f else 1f
            val t = ((x - edge0) / (edge1 - edge0)).coerceIn(0f, 1f)
            return t * t * (3f - 2f * t)
        }

        internal fun lerp(a: Float, b: Float, t: Float) = a + (b - a) * t
    }
}
