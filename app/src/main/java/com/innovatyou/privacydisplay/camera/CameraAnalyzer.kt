package com.innovatyou.privacydisplay.camera

import android.os.SystemClock
import android.util.Log
import androidx.camera.core.ExperimentalGetImage
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.ImageProxy
import com.google.android.gms.tasks.Tasks
import com.google.mlkit.vision.common.InputImage
import com.google.mlkit.vision.face.Face
import com.google.mlkit.vision.face.FaceDetector
import com.google.mlkit.vision.face.FaceLandmark
import com.innovatyou.privacydisplay.owner.OwnerCheck
import com.innovatyou.privacydisplay.owner.OwnerMatching
import kotlin.math.abs

/**
 * Runs face detection on at most one frame every [minIntervalMs] and, when [ownerCheck] is set,
 * checks the main face against the owner's face print at most every [ownerIntervalMs].
 *
 * Runs on the analysis thread and blocks it while a frame is processed, so CameraX simply drops
 * frames that arrive in the meantime. Frames are closed straight after; nothing is stored.
 */
class CameraAnalyzer(
    private val detector: FaceDetector,
    private val minIntervalMs: Long,
    private val onResult: (FaceObservation, OwnerCheck?) -> Unit,
    private val ownerCheck: OwnerCheckFn? = null,
    private val ownerIntervalMs: Long = 1_000L,
    private val clock: () -> Long = SystemClock::elapsedRealtime,
) : ImageAnalysis.Analyzer {

    /** Compares one face in one frame with the owner. Receives the frame while it is still open. */
    fun interface OwnerCheckFn {
        fun check(image: ImageProxy, landmarks: FloatArray): OwnerCheck
    }

    private var lastRunAt = Long.MIN_VALUE / 2
    private var lastOwnerCheckAt = Long.MIN_VALUE / 2

    @androidx.annotation.OptIn(markerClass = [ExperimentalGetImage::class])
    override fun analyze(image: ImageProxy) {
        image.use {
            val now = clock()
            val mediaImage = image.image ?: return
            if (now - lastRunAt < minIntervalMs) return
            lastRunAt = now

            val rotation = image.imageInfo.rotationDegrees
            val sideways = rotation == 90 || rotation == 270
            val uprightWidth = (if (sideways) image.height else image.width).toFloat()
            val uprightHeight = (if (sideways) image.width else image.height).toFloat()

            val faces = try {
                Tasks.await(detector.process(InputImage.fromMediaImage(mediaImage, rotation)))
            } catch (e: Exception) {
                Log.w(TAG, "Face detection failed", e)
                return
            }
            val observation = faces.toObservation(uprightWidth, uprightHeight)

            var owner: OwnerCheck? = null
            val check = ownerCheck
            if (check != null && now - lastOwnerCheckAt >= ownerIntervalMs) {
                lastOwnerCheckAt = now
                owner = ownerCheckFor(faces, uprightWidth, image, check)
            }
            onResult(observation, owner)
        }
    }

    private fun ownerCheckFor(faces: List<Face>, imageWidth: Float, image: ImageProxy, check: OwnerCheckFn): OwnerCheck {
        val primary = faces.maxByOrNull { it.boundingBox.width() * it.boundingBox.height() }
            ?: return OwnerCheck.UNCLEAR
        val usable = OwnerMatching.isUsableFace(
            primary.boundingBox.width() / imageWidth, primary.headEulerAngleY, primary.headEulerAngleX,
        )
        val landmarks = primary.alignmentLandmarks()
        if (!usable || landmarks == null) return OwnerCheck.UNCLEAR
        return try {
            check.check(image, landmarks)
        } catch (e: Exception) {
            Log.w(TAG, "Owner check failed", e)
            OwnerCheck.UNCLEAR
        }
    }

    private companion object {
        const val TAG = "CameraAnalyzer"
    }
}

/** Head rotation (degrees) beyond which a face is treated as looking away. */
const val FACING_MAX_ANGLE = 30f

fun isFacing(yawDegrees: Float, pitchDegrees: Float): Boolean =
    abs(yawDegrees) <= FACING_MAX_ANGLE && abs(pitchDegrees) <= FACING_MAX_ANGLE

/**
 * The primary user is assumed to be the largest (closest) face; the next largest is the additional
 * viewer. Bounding boxes are in upright image coordinates.
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

/**
 * The five landmarks used for alignment, in the order of the recognition template (image left to
 * right). ML Kit names landmarks from the person's point of view, so the person's right eye is on
 * the left of the (unmirrored) image.
 */
fun Face.alignmentLandmarks(): FloatArray? {
    val types = intArrayOf(
        FaceLandmark.RIGHT_EYE, FaceLandmark.LEFT_EYE, FaceLandmark.NOSE_BASE,
        FaceLandmark.MOUTH_RIGHT, FaceLandmark.MOUTH_LEFT,
    )
    val out = FloatArray(10)
    types.forEachIndexed { i, type ->
        val point = getLandmark(type)?.position ?: return null
        out[2 * i] = point.x
        out[2 * i + 1] = point.y
    }
    return out
}
