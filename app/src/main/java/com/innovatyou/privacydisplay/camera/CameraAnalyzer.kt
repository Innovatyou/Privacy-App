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
        val rotation = image.imageInfo.rotationDegrees
        val sideways = rotation == 90 || rotation == 270
        val uprightWidth = if (sideways) image.height else image.width
        val uprightHeight = if (sideways) image.width else image.height
        val input = InputImage.fromMediaImage(mediaImage, rotation)
        detector.process(input)
            .addOnSuccessListener { faces ->
                onResult(faces.toObservation(uprightWidth.toFloat(), uprightHeight.toFloat()))
            }
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

        /**
         * The primary user is assumed to be the largest (closest) face; the next largest is the
         * additional viewer. No identity matching. Bounding boxes are in upright image coordinates.
         */
        fun List<Face>.toObservation(imageWidth: Float, imageHeight: Float): FaceObservation {
            val bySize = sortedByDescending { it.boundingBox.width() * it.boundingBox.height() }
            val primary = bySize.firstOrNull()
            val extra = bySize.getOrNull(1)
            val side = if (primary != null && extra != null) {
                ViewerGeometry.sideOf(
                    extra.boundingBox.exactCenterX(), extra.boundingBox.exactCenterY(),
                    primary.boundingBox.exactCenterX(), primary.boundingBox.exactCenterY(),
                    imageWidth, imageHeight,
                )
            } else {
                null
            }
            return FaceObservation(
                faceCount = size,
                primaryFacing = primary != null && isFacing(primary.headEulerAngleY, primary.headEulerAngleX),
                extraViewerSide = side,
            )
        }
    }
}
