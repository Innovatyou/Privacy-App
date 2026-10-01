package com.innovatyou.privacydisplay.owner

import ai.onnxruntime.OnnxTensor
import ai.onnxruntime.OrtEnvironment
import ai.onnxruntime.OrtSession
import android.content.Context
import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.Matrix
import android.graphics.Paint
import android.graphics.RectF
import dagger.hilt.android.qualifiers.ApplicationContext
import java.nio.FloatBuffer
import javax.inject.Inject
import javax.inject.Singleton

/** A face print, or null when the face was too dark, plus the face's brightness (0–255). */
class FaceSample(val embedding: FloatArray?, val luma: Float)

/**
 * Turns a face into a 128-number face print with the SFace model, entirely on the device.
 * The aligned 112x112 face crop only exists in memory for the duration of the call.
 */
@Singleton
class FaceEmbedder @Inject constructor(
    @ApplicationContext private val context: Context,
) {
    private val environment: OrtEnvironment by lazy { OrtEnvironment.getEnvironment() }
    private val session: OrtSession by lazy {
        val model = context.assets.open(MODEL_ASSET).use { it.readBytes() }
        environment.createSession(model, OrtSession.SessionOptions())
    }
    private val inputName: String by lazy { session.inputNames.first() }

    /**
     * @param frame camera frame as delivered by the camera (not rotated).
     * @param rotationDegrees rotation that makes [frame] upright.
     * @param landmarks five landmarks in upright coordinates, in [FaceAlignment.TEMPLATE] order.
     * @return L2-normalized face print.
     */
    @Synchronized
    fun embed(frame: Bitmap, rotationDegrees: Int, landmarks: FloatArray): FloatArray =
        run(FaceAlignment.toModelInput(alignedPixels(frame, rotationDegrees, landmarks)))

    /**
     * Like [embed], but first measures how bright the aligned face is and skips the model when it
     * is darker than [minLuma] (a face print from a near-black image is meaningless).
     */
    @Synchronized
    fun embedIfBright(frame: Bitmap, rotationDegrees: Int, landmarks: FloatArray, minLuma: Float): FaceSample {
        val pixels = alignedPixels(frame, rotationDegrees, landmarks)
        val luma = LowLight.meanLuma(pixels)
        val embedding = if (luma >= minLuma) run(FaceAlignment.toModelInput(pixels)) else null
        return FaceSample(embedding, luma)
    }

    private fun alignedPixels(frame: Bitmap, rotationDegrees: Int, landmarks: FloatArray): IntArray {
        val aligned = align(frame, rotationDegrees, landmarks)
        val pixels = IntArray(FaceAlignment.SIZE * FaceAlignment.SIZE)
        aligned.getPixels(pixels, 0, FaceAlignment.SIZE, 0, 0, FaceAlignment.SIZE, FaceAlignment.SIZE)
        aligned.recycle()
        return pixels
    }

    /** Runs the model on an already prepared input (RGB, 0–255, NCHW 1x3x112x112). */
    @Synchronized
    fun run(input: FloatArray): FloatArray {
        val shape = longArrayOf(1, 3, FaceAlignment.SIZE.toLong(), FaceAlignment.SIZE.toLong())
        OnnxTensor.createTensor(environment, FloatBuffer.wrap(input), shape).use { tensor ->
            session.run(mapOf(inputName to tensor)).use { result ->
                @Suppress("UNCHECKED_CAST")
                val output = (result[0].value as Array<FloatArray>)[0]
                return OwnerMatching.normalize(output)
            }
        }
    }

    private fun align(frame: Bitmap, rotationDegrees: Int, landmarks: FloatArray): Bitmap {
        // Raw frame -> upright image.
        val matrix = Matrix().apply { setRotate(rotationDegrees.toFloat()) }
        val bounds = RectF(0f, 0f, frame.width.toFloat(), frame.height.toFloat())
        matrix.mapRect(bounds)
        matrix.postTranslate(-bounds.left, -bounds.top)
        // Upright image -> 112x112 template.
        val t = FaceAlignment.similarityTransform(landmarks)
        val toTemplate = Matrix().apply {
            setValues(floatArrayOf(t[0], t[1], t[2], t[3], t[4], t[5], 0f, 0f, 1f))
        }
        matrix.postConcat(toTemplate)

        val out = Bitmap.createBitmap(FaceAlignment.SIZE, FaceAlignment.SIZE, Bitmap.Config.ARGB_8888)
        Canvas(out).drawBitmap(frame, matrix, Paint(Paint.FILTER_BITMAP_FLAG))
        return out
    }

    private companion object {
        const val MODEL_ASSET = "face_recognition_sface.onnx"
    }
}
