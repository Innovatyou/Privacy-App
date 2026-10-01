package com.innovatyou.privacydisplay.owner

import java.nio.ByteBuffer

/**
 * Brightness measurements used to tell "it is too dark to recognise anyone" apart from "this is
 * a different person", and to drive the camera exposure and the optional screen glow.
 * Values are average luma (brightness) on a 0–255 scale.
 */
object LowLight {
    /** Below this, a frame without a detectable face counts as too dark. */
    const val DARK_FRAME_LUMA = 30f

    /** Below this, the aligned face is too dark for a reliable owner check. */
    const val MIN_FACE_LUMA = 50f

    /** Below these, the scene counts as dim (raise exposure, offer the screen glow). */
    const val DIM_FRAME_LUMA = 70f
    const val DIM_FACE_LUMA = 80f

    /** Above this, the scene is bright enough to go back to normal exposure. */
    const val BRIGHT_FRAME_LUMA = 140f

    /**
     * Average of a camera Y (luma) plane, sampling every [step]-th pixel in both directions.
     * Uses absolute reads, so the buffer position is left untouched.
     */
    fun meanLuma(plane: ByteBuffer, rowStride: Int, pixelStride: Int, width: Int, height: Int, step: Int = 8): Float {
        if (width <= 0 || height <= 0) return 0f
        var sum = 0L
        var count = 0
        var y = 0
        while (y < height) {
            val row = y * rowStride
            var x = 0
            while (x < width) {
                val index = row + x * pixelStride
                if (index < plane.limit()) {
                    sum += plane.get(index).toInt() and 0xFF
                    count++
                }
                x += step
            }
            y += step
        }
        return if (count == 0) 0f else sum.toFloat() / count
    }

    /** Average luma of ARGB pixels (for example the aligned 112x112 face). */
    fun meanLuma(argb: IntArray): Float {
        if (argb.isEmpty()) return 0f
        var sum = 0.0
        for (p in argb) {
            val r = (p shr 16) and 0xFF
            val g = (p shr 8) and 0xFF
            val b = p and 0xFF
            sum += 0.299 * r + 0.587 * g + 0.114 * b
        }
        return (sum / argb.size).toFloat()
    }

    fun isDim(frameLuma: Float, faceLuma: Float?): Boolean =
        frameLuma < DIM_FRAME_LUMA || (faceLuma != null && faceLuma < DIM_FACE_LUMA)

    /**
     * Exposure compensation to request: the maximum when dim, 0 when bright again, or null to keep
     * the current value (the gap between the two thresholds avoids flickering back and forth).
     */
    fun exposureTarget(frameLuma: Float, maxIndex: Int): Int? = when {
        maxIndex <= 0 -> null
        frameLuma < DIM_FRAME_LUMA -> maxIndex
        frameLuma > BRIGHT_FRAME_LUMA -> 0
        else -> null
    }
}

/**
 * Liveness check against printed photos: notices a blink (eyes open, then closed, then open
 * again) from ML Kit's eye-open probabilities. A photo cannot blink. A video still can, which is
 * why the fingerprint/PIN remains the secure way to unlock.
 */
class BlinkDetector {
    private enum class Phase { WAITING_OPEN, OPEN, CLOSED }

    private var phase = Phase.WAITING_OPEN
    var lastBlinkAt: Long? = null
        private set

    /** @param eyesOpen average probability (0–1) that the eyes are open, or null without a face. */
    fun update(eyesOpen: Float?, nowMs: Long) {
        if (eyesOpen == null) {
            phase = Phase.WAITING_OPEN
            return
        }
        phase = when (phase) {
            Phase.WAITING_OPEN -> if (eyesOpen >= OPEN) Phase.OPEN else Phase.WAITING_OPEN
            Phase.OPEN -> if (eyesOpen <= CLOSED) Phase.CLOSED else Phase.OPEN
            Phase.CLOSED -> if (eyesOpen >= OPEN) {
                lastBlinkAt = nowMs
                Phase.OPEN
            } else {
                Phase.CLOSED
            }
        }
    }

    fun blinkedWithin(windowMs: Long, nowMs: Long): Boolean =
        lastBlinkAt?.let { nowMs - it <= windowMs } ?: false

    companion object {
        const val OPEN = 0.7f
        const val CLOSED = 0.3f

        /** How recent a blink must be for the face to unblock the phone. */
        const val WINDOW_MS = 8_000L
    }
}
