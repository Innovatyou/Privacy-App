package com.innovatyou.privacydisplay.camera

import org.junit.Assert.assertEquals
import org.junit.Test

class ViewerStateSmootherTest {

    private val one = FaceObservation(faceCount = 1, primaryFacing = true)
    private val oneAway = FaceObservation(faceCount = 1, primaryFacing = false)
    private val none = FaceObservation(faceCount = 0, primaryFacing = false)
    private val two = FaceObservation(faceCount = 2, primaryFacing = true)

    private val smoother = ViewerStateSmoother(noFaceGraceMs = 2_000, multipleConfirmations = 2, multipleReleaseMs = 3_000)

    @Test
    fun `single facing face means the user is present`() {
        assertEquals(ViewerState.USER_PRESENT, smoother.update(one, 0))
        assertEquals(ViewerState.LOOKING_AWAY, smoother.update(oneAway, 500))
    }

    @Test
    fun `no face is only reported after the grace period`() {
        assertEquals(ViewerState.USER_PRESENT, smoother.update(one, 0))
        assertEquals(ViewerState.USER_PRESENT, smoother.update(none, 1_000))
        assertEquals(ViewerState.NO_FACE, smoother.update(none, 2_500))
        assertEquals(ViewerState.USER_PRESENT, smoother.update(one, 3_000))
    }

    @Test
    fun `no face at start stays starting until the grace period ends`() {
        assertEquals(ViewerState.STARTING, smoother.update(none, 0))
        assertEquals(ViewerState.STARTING, smoother.update(none, 1_500))
        assertEquals(ViewerState.NO_FACE, smoother.update(none, 2_000))
    }

    @Test
    fun `a single frame with two faces is not enough`() {
        smoother.update(one, 0)
        assertEquals(ViewerState.USER_PRESENT, smoother.update(two, 500))
        assertEquals(ViewerState.USER_PRESENT, smoother.update(one, 1_000))
    }

    @Test
    fun `multiple viewers are held for the release time`() {
        smoother.update(two, 0)
        assertEquals(ViewerState.MULTIPLE_VIEWERS, smoother.update(two, 500))
        assertEquals(ViewerState.MULTIPLE_VIEWERS, smoother.update(one, 2_000))
        assertEquals(ViewerState.USER_PRESENT, smoother.update(one, 4_000))
    }

    @Test
    fun `facing check uses the head angles`() {
        assertEquals(true, CameraAnalyzer.isFacing(10f, -5f))
        assertEquals(false, CameraAnalyzer.isFacing(45f, 0f))
        assertEquals(false, CameraAnalyzer.isFacing(0f, -40f))
    }
}
