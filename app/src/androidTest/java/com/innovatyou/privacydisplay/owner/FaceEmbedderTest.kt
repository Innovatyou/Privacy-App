package com.innovatyou.privacydisplay.owner

import android.graphics.Bitmap
import android.graphics.Color
import androidx.test.ext.junit.runners.AndroidJUnit4
import androidx.test.platform.app.InstrumentationRegistry
import org.junit.Assert.assertArrayEquals
import org.junit.Assert.assertEquals
import org.junit.Test
import org.junit.runner.RunWith
import kotlin.math.sqrt

/** Runs the bundled SFace model with ONNX Runtime on the device. */
@RunWith(AndroidJUnit4::class)
class FaceEmbedderTest {

    private val embedder = FaceEmbedder(InstrumentationRegistry.getInstrumentation().targetContext)

    @Test
    fun matchesTheReferenceOutput() {
        val size = 3 * FaceAlignment.SIZE * FaceAlignment.SIZE
        val input = FloatArray(size) { ((it * 37) % 256).toFloat() }
        val output = embedder.run(input)
        assertEquals(128, output.size)
        // Same input run through the same model with ONNX Runtime on a desktop.
        val expected = floatArrayOf(-0.177385f, -0.015723f, 0.073229f, -0.081724f, 0.004683f, 0.097373f, -0.032617f, -0.089116f)
        assertArrayEquals(expected, output.copyOf(8), 2e-3f)
        assertEquals(1f, sqrt(output.sumOf { (it * it).toDouble() }).toFloat(), 1e-3f)
    }

    @Test
    fun alignedFaceKeepsItsBrightnessAtEveryRotation() {
        // A uniform frame: if alignment placed the crop outside the image, black borders would
        // pull the brightness down and the face would wrongly count as too dark.
        val frame = Bitmap.createBitmap(480, 640, Bitmap.Config.ARGB_8888).apply { eraseColor(Color.rgb(180, 140, 120)) }
        val landmarks = floatArrayOf(200f, 250f, 280f, 250f, 240f, 300f, 210f, 350f, 270f, 350f)
        for (rotation in listOf(0, 90, 180, 270)) {
            val sample = embedder.embedIfVisible(frame, rotation, landmarks)
            assertEquals("brightness at $rotation", 149.7f, sample.light.mean, 3f)
            assertEquals(false, sample.light.tooDark)
            assertEquals(128, sample.embedding?.size)
        }
    }

    @Test
    fun aBlackFrameIsTooDark() {
        val frame = Bitmap.createBitmap(480, 640, Bitmap.Config.ARGB_8888).apply { eraseColor(Color.rgb(5, 5, 5)) }
        val landmarks = floatArrayOf(200f, 250f, 280f, 250f, 240f, 300f, 210f, 350f, 270f, 350f)
        val sample = embedder.embedIfVisible(frame, 0, landmarks)
        assertEquals(true, sample.light.tooDark)
        assertEquals(null, sample.embedding)
    }

    @Test
    fun embedsAnAlignedFrame() {
        val frame = Bitmap.createBitmap(480, 640, Bitmap.Config.ARGB_8888).apply { eraseColor(Color.rgb(180, 140, 120)) }
        val landmarks = floatArrayOf(200f, 250f, 280f, 250f, 240f, 300f, 210f, 350f, 270f, 350f)
        val a = embedder.embed(frame, rotationDegrees = 270, landmarks = landmarks)
        val b = embedder.embed(frame, rotationDegrees = 270, landmarks = landmarks)
        assertEquals(128, a.size)
        assertArrayEquals(a, b, 1e-6f)
    }
}
