package com.innovatyou.privacydisplay.camera

import org.junit.Assert.assertEquals
import org.junit.Test

class ViewerGeometryTest {

    private fun side(extraX: Float, extraY: Float) =
        ViewerGeometry.sideOf(extraX, extraY, userX = 240f, userY = 320f, width = 480f, height = 640f)

    @Test
    fun `front camera image is not mirrored so image right is the user's left`() {
        assertEquals(ViewerSide.LEFT, side(420f, 330f))
        assertEquals(ViewerSide.RIGHT, side(60f, 300f))
    }

    @Test
    fun `vertical offsets map to above and below`() {
        assertEquals(ViewerSide.ABOVE, side(250f, 60f))
        assertEquals(ViewerSide.BELOW, side(230f, 600f))
    }

    @Test
    fun `the larger normalised offset wins`() {
        // 100/480 horizontally beats 100/640 vertically.
        assertEquals(ViewerSide.LEFT, side(340f, 220f))
    }
}
