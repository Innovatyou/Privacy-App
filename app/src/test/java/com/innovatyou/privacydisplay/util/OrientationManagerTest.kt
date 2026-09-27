package com.innovatyou.privacydisplay.util

import android.view.Surface
import org.junit.Assert.assertEquals
import org.junit.Test

class OrientationManagerTest {

    @Test
    fun `phone orientation follows rotation`() {
        assertEquals(DeviceOrientation.PORTRAIT, OrientationManager.orientationFor(Surface.ROTATION_0, 1080, 2400))
        assertEquals(DeviceOrientation.LANDSCAPE, OrientationManager.orientationFor(Surface.ROTATION_90, 1080, 2400))
        assertEquals(DeviceOrientation.PORTRAIT, OrientationManager.orientationFor(Surface.ROTATION_180, 1080, 2400))
        assertEquals(DeviceOrientation.LANDSCAPE, OrientationManager.orientationFor(Surface.ROTATION_270, 1080, 2400))
    }

    @Test
    fun `tablets that are naturally landscape are handled`() {
        assertEquals(DeviceOrientation.LANDSCAPE, OrientationManager.orientationFor(Surface.ROTATION_0, 2560, 1600))
        assertEquals(DeviceOrientation.PORTRAIT, OrientationManager.orientationFor(Surface.ROTATION_90, 2560, 1600))
    }
}
