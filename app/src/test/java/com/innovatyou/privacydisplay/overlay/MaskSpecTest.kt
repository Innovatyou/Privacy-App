package com.innovatyou.privacydisplay.overlay

import com.innovatyou.privacydisplay.data.MaskMode
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class MaskSpecTest {

    private fun params(
        mode: MaskMode,
        strength: Float = 1f,
        width: Float = 0.5f,
        height: Float = 0.5f,
        edge: Float = 1f,
        gradient: Float = 0.1f,
    ) = MaskParams(mode, strength, width, height, edge, gradient)

    @Test
    fun `clear area is centred and sized from the fractions`() {
        val spec = MaskSpec.compute(params(MaskMode.BLACK), 1000f, 2000f)
        assertEquals(250f, spec.clearLeft, 0.01f)
        assertEquals(750f, spec.clearRight, 0.01f)
        assertEquals(500f, spec.clearTop, 0.01f)
        assertEquals(1500f, spec.clearBottom, 0.01f)
    }

    @Test
    fun `black mask is clear inside and solid outside with a hard edge`() {
        val spec = MaskSpec.compute(params(MaskMode.BLACK, strength = 0.7f), 1000f, 2000f)
        assertEquals(0f, spec.alphaAt(500f, 1000f), 0.001f)
        assertEquals(0.7f, spec.alphaAt(249f, 1000f), 0.001f)
        assertEquals(0.7f, spec.alphaAt(0f, 0f), 0.001f)
        assertTrue(spec.hardEdge)
    }

    @Test
    fun `dark edges fade in over the gradient width`() {
        // Feather = 10% of the shorter side = 100 px.
        val spec = MaskSpec.compute(params(MaskMode.DARK_EDGES, strength = 1f, edge = 0.8f), 1000f, 2000f)
        assertEquals(0f, spec.alphaAt(500f, 1000f), 0.001f)
        val halfway = spec.alphaAt(200f, 1000f)
        assertTrue("halfway alpha $halfway", halfway > 0.1f && halfway < 0.7f)
        assertEquals(0.8f, spec.alphaAt(100f, 1000f), 0.001f)
        assertEquals(0.8f, spec.alphaAt(0f, 0f), 0.001f)
    }

    @Test
    fun `narrow window shrinks the clear area`() {
        val spec = MaskSpec.compute(params(MaskMode.NARROW_WINDOW), 1000f, 2000f)
        assertEquals(1000f * 0.5f * MaskSpec.NARROW_WINDOW_SCALE, spec.clearRight - spec.clearLeft, 0.01f)
        assertEquals(2000f * 0.5f * MaskSpec.NARROW_WINDOW_SCALE, spec.clearBottom - spec.clearTop, 0.01f)
    }

    @Test
    fun `landscape keeps the clear area the same physical shape`() {
        val portrait = MaskSpec.compute(params(MaskMode.BLACK, width = 0.6f, height = 0.4f), 1000f, 2000f)
        val landscape = MaskSpec.compute(params(MaskMode.BLACK, width = 0.6f, height = 0.4f), 2000f, 1000f)
        assertEquals(portrait.clearRight - portrait.clearLeft, landscape.clearBottom - landscape.clearTop, 0.01f)
        assertEquals(portrait.clearBottom - portrait.clearTop, landscape.clearRight - landscape.clearLeft, 0.01f)
    }

    @Test
    fun `gradient mask gets darker toward the edges`() {
        val spec = MaskSpec.compute(params(MaskMode.GRADIENT, width = 0.4f, height = 0.4f), 1000f, 2000f)
        assertEquals(0f, spec.alphaAt(500f, 1000f), 0.001f)
        var previous = -1f
        for (x in listOf(500f, 350f, 250f, 150f, 50f, 0f)) {
            val alpha = spec.alphaAt(x, 1000f)
            assertTrue("alpha must not decrease toward the edge", alpha >= previous)
            previous = alpha
        }
        assertEquals(1f, spec.alphaAt(0f, 1000f), 0.001f)
    }

    @Test
    fun `custom mode dims the clear area with the strength`() {
        val spec = MaskSpec.compute(params(MaskMode.CUSTOM, strength = 0.6f, edge = 0.9f), 1000f, 2000f)
        assertEquals(0.6f * MaskSpec.CUSTOM_CENTER_FACTOR, spec.alphaAt(500f, 1000f), 0.001f)
        assertEquals(0.9f, spec.alphaAt(0f, 0f), 0.001f)
    }

    @Test
    fun `full cover hides the whole screen`() {
        val spec = MaskSpec.compute(params(MaskMode.GRADIENT, strength = 0.2f).copy(fullCover = true), 1000f, 2000f)
        assertEquals(1f, spec.alphaAt(500f, 1000f), 0f)
        assertEquals(1f, spec.alphaAt(0f, 0f), 0f)
        assertTrue(spec.hardEdge)
    }

    @Test
    fun `frosted mode is a soft frosted mask`() {
        val spec = MaskSpec.compute(params(MaskMode.FROSTED, strength = 1f, edge = 0.8f), 1000f, 2000f)
        assertTrue(spec.frosted)
        assertTrue(!spec.hardEdge)
        assertEquals(0f, spec.alphaAt(500f, 1000f), 0.001f)
        assertEquals(0.8f, spec.alphaAt(0f, 0f), 0.001f)
    }

    @Test
    fun `zero strength black mask draws nothing`() {
        assertTrue(MaskSpec.compute(params(MaskMode.BLACK, strength = 0f), 1000f, 2000f).isEmpty)
    }

    @Test
    fun `out of range values are clamped`() {
        val spec = MaskSpec.compute(params(MaskMode.BLACK, strength = 3f, width = 5f, height = -1f), 1000f, 2000f)
        assertEquals(1f, spec.outsideAlpha, 0.001f)
        assertEquals(1000f, spec.clearRight - spec.clearLeft, 0.01f)
        assertEquals(200f, spec.clearBottom - spec.clearTop, 0.01f) // Minimum clear area (10%).
    }

    @Test
    fun `smoothstep is clamped and smooth`() {
        assertEquals(0f, MaskSpec.smoothstep(0f, 10f, -5f), 0f)
        assertEquals(0.5f, MaskSpec.smoothstep(0f, 10f, 5f), 0.0001f)
        assertEquals(1f, MaskSpec.smoothstep(0f, 10f, 50f), 0f)
    }
}
