package com.innovatyou.privacydisplay.owner

import java.nio.ByteBuffer
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class LowLightTest {

    @Test
    fun `mean luma of a camera plane respects row stride`() {
        // 4x2 image with 2 bytes of padding per row: rows are 10 and 200.
        val width = 4
        val height = 2
        val rowStride = 6
        val buffer = ByteBuffer.allocate(rowStride * height)
        for (x in 0 until width) {
            buffer.put(x, 10)
            buffer.put(rowStride + x, 200.toByte())
        }
        // Padding bytes are garbage and must be ignored.
        buffer.put(4, 255.toByte()); buffer.put(5, 255.toByte())
        assertEquals(105f, LowLight.meanLuma(buffer, rowStride, 1, width, height, step = 1), 0.001f)
        assertEquals(0, buffer.position())
    }

    @Test
    fun `mean luma of ARGB pixels uses perceived brightness`() {
        assertEquals(255f, LowLight.meanLuma(intArrayOf(0xFFFFFFFF.toInt())), 0.01f)
        assertEquals(0f, LowLight.meanLuma(intArrayOf(0xFF000000.toInt())), 0.01f)
        assertEquals(0.587f * 255, LowLight.meanLuma(intArrayOf(0xFF00FF00.toInt())), 0.01f)
    }

    @Test
    fun `exposure goes up when dim and back down when bright`() {
        assertEquals(12, LowLight.exposureTarget(20f, maxIndex = 12))
        assertEquals(0, LowLight.exposureTarget(200f, maxIndex = 12))
        assertNull(LowLight.exposureTarget(100f, maxIndex = 12)) // In between: keep the current value.
        assertNull(LowLight.exposureTarget(20f, maxIndex = 0)) // Not supported.
    }

    @Test
    fun `dim scene or dim face counts as low light`() {
        assertTrue(LowLight.isDim(frameLuma = 40f, faceLuma = null))
        assertTrue(LowLight.isDim(frameLuma = 120f, faceLuma = 60f))
        assertFalse(LowLight.isDim(frameLuma = 120f, faceLuma = 120f))
    }

    @Test
    fun `a blink is open then closed then open`() {
        val blinks = BlinkDetector()
        blinks.update(0.9f, 0)
        blinks.update(0.1f, 100)
        assertFalse(blinks.blinkedWithin(BlinkDetector.WINDOW_MS, 100))
        blinks.update(0.95f, 250)
        assertTrue(blinks.blinkedWithin(BlinkDetector.WINDOW_MS, 300))
        assertFalse(blinks.blinkedWithin(BlinkDetector.WINDOW_MS, 250 + BlinkDetector.WINDOW_MS + 1))
    }

    @Test
    fun `a photo with eyes always open never blinks`() {
        val blinks = BlinkDetector()
        for (t in 0L until 5_000L step 120L) blinks.update(0.92f, t)
        assertFalse(blinks.blinkedWithin(BlinkDetector.WINDOW_MS, 5_000))
    }

    @Test
    fun `losing the face resets the blink sequence`() {
        val blinks = BlinkDetector()
        blinks.update(0.9f, 0)
        blinks.update(0.1f, 100)
        blinks.update(null, 200)
        blinks.update(0.9f, 300)
        assertFalse(blinks.blinkedWithin(BlinkDetector.WINDOW_MS, 300))
    }
}
