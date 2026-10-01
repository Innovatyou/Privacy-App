package com.innovatyou.privacydisplay.owner

import org.junit.Assert.assertArrayEquals
import org.junit.Assert.assertEquals
import org.junit.Test
import kotlin.math.cos
import kotlin.math.sin

class FaceAlignmentTest {

    @Test
    fun `matches OpenCV alignment for the same landmarks`() {
        // Reference computed with OpenCV FaceRecognizerSF.alignCrop's similarity transform.
        val landmarks = floatArrayOf(210.5f, 180.2f, 290.1f, 175.9f, 250.0f, 230.4f, 220.3f, 280.8f, 285.6f, 277.1f)
        val expected = floatArrayOf(0.416622f, -0.013022f, -45.690393f, 0.013022f, 0.416622f, -26.728053f)
        assertArrayEquals(expected, FaceAlignment.similarityTransform(landmarks), 1e-3f)
    }

    @Test
    fun `recovers a known rotation, scale and shift exactly`() {
        val angle = 0.3
        val scale = 2.5f
        val src = FaceAlignment.TEMPLATE
        // dst = scale * R * src + (10, -4); the transform from src to dst must be exactly that.
        val dst = FloatArray(src.size)
        for (i in 0 until src.size / 2) {
            val x = src[2 * i]; val y = src[2 * i + 1]
            dst[2 * i] = (scale * (cos(angle) * x - sin(angle) * y)).toFloat() + 10f
            dst[2 * i + 1] = (scale * (sin(angle) * x + cos(angle) * y)).toFloat() - 4f
        }
        val t = FaceAlignment.similarityTransform(src, dst)
        assertEquals(scale * cos(angle).toFloat(), t[0], 1e-4f)
        assertEquals(-scale * sin(angle).toFloat(), t[1], 1e-4f)
        assertEquals(10f, t[2], 1e-3f)
        assertEquals(-4f, t[5], 1e-3f)
    }

    @Test
    fun `template landmarks map to themselves`() {
        val t = FaceAlignment.similarityTransform(FaceAlignment.TEMPLATE)
        assertArrayEquals(floatArrayOf(1f, 0f, 0f, 0f, 1f, 0f), t, 1e-4f)
    }

    @Test
    fun `model input is RGB planes in 0 to 255`() {
        val pixels = IntArray(FaceAlignment.SIZE * FaceAlignment.SIZE) { 0xFF102030.toInt() }
        val input = FaceAlignment.toModelInput(pixels)
        val area = FaceAlignment.SIZE * FaceAlignment.SIZE
        assertEquals(3 * area, input.size)
        assertEquals(16f, input[0], 0f) // R
        assertEquals(32f, input[area], 0f) // G
        assertEquals(48f, input[2 * area], 0f) // B
    }
}
