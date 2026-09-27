package com.innovatyou.privacydisplay.camera

import android.os.SystemClock
import androidx.camera.core.ExperimentalGetImage
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.ImageProxy
import com.google.mlkit.vision.common.InputImage
import com.google.mlkit.vision.face.Face
import com.google.mlkit.vision.face.FaceDetector
import java.util.concurrent.atomic.AtomicBoolean
import kotlin.math.abs

/**
 * Runs face detection on at most one frame every [minIntervalMs]. Frames are only held in memory
 * for the duration of the analysis and are closed straight after; nothing is stored or uploaded.
 */
class CameraAnalyzer(
    private val detector: FaceDetector,
    private val minIntervalMs: Long,
    private val onResult: (FaceObservation) -> Unit,
    private val onError: (Exception) -> Unit,
    private val clock: () -> Long = SystemClock::elapsedRealtime,
) : ImageAnalysis.Analyzer {

    private val busy = AtomicBoolean(false)
    private var lastRunAt = Long.MIN_VALUE / 2

    @androidx.annotation.OptIn(markerClass = [ExperimentalGetImage::class])
    override fun analyze(image: ImageProxy) {
        val now = clock()
        val mediaImage = image.image
        if (mediaImage == null || now - lastRunAt < minIntervalMs || !busy.compareAndSet(false, true)) {
            image.close()
            return
        }
        lastRunAt = now
        val input = InputImage.fromMediaImage(mediaImage, image.imageInfo.rotationDegrees)
        detector.process(input)
            .addOnSuccessListener { faces -> onResult(faces.toObservation()) }
            .addOnFailureListener { e -> onError(e) }
            .addOnCompleteListener {
                image.close()
                busy.set(false)
            }
    }

    companion object {
        /** Head rotation (degrees) beyond which a face is treated as looking away. */
        const val FACING_MAX_ANGLE = 30f

        fun isFacing(yawDegrees: Float, pitchDegrees: Float): Boolean =
            abs(yawDegrees) <= FACING_MAX_ANGLE && abs(pitchDegrees) <= FACING_MAX_ANGLE

        /** The primary user is assumed to be the largest (closest) face. No identity matching. */
        fun List<Face>.toObservation(): FaceObservation {
            val primary = maxByOrNull { it.boundingBox.width() * it.boundingBox.height() }
            return FaceObservation(
                faceCount = size,
                primaryFacing = primary != null && isFacing(primary.headEulerAngleY, primary.headEulerAngleX),
            )
        }
    }
}
