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
import com.innovatyou.privacydisplay.owner.FaceLight
import com.innovatyou.privacydisplay.owner.LowLight
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
    private val onResult: (FaceObservation, OwnerCheck?, FrameStats) -> Unit,
    private val ownerCheck: OwnerCheckFn? = null,
    private val ownerIntervalMs: Long = 1_000L,
    private val clock: () -> Long = SystemClock::elapsedRealtime,
) : ImageAnalysis.Analyzer {

    /** Compares one face in one frame with the owner. Receives the frame while it is still open. */
    fun interface OwnerCheckFn {
        fun check(image: ImageProxy, landmarks: FloatArray): OwnerResult
    }

    /** Owner check result plus the light on the face (null when no face was checked). */
    data class OwnerResult(val check: OwnerCheck, val faceLight: FaceLight? = null)

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
            val plane = image.planes[0]
            val frameLuma = LowLight.meanLuma(plane.buffer, plane.rowStride, plane.pixelStride, image.width, image.height)
            val primary = faces.maxByOrNull { it.boundingBox.width() * it.boundingBox.height() }

            var owner: OwnerResult? = null
            val check = ownerCheck
            if (check != null && now - lastOwnerCheckAt >= ownerIntervalMs) {
                lastOwnerCheckAt = now
                owner = ownerCheckFor(primary, frameLuma, uprightWidth, image, check)
            }
            val faceCenter = primary?.let {
                LowLight.uprightToRaw(
                    it.boundingBox.exactCenterX(), it.boundingBox.exactCenterY(), rotation, image.width, image.height,
                )
            }
            onResult(
                observation,
                owner?.check,
                FrameStats(frameLuma, owner?.faceLight, primary?.eyesOpen(), faceCenter, image.width, image.height),
            )
        }
    }

    private fun ownerCheckFor(
        primary: Face?,
        frameLuma: Float,
        imageWidth: Float,
        image: ImageProxy,
        check: OwnerCheckFn,
    ): OwnerResult {
        val dark = frameLuma < LowLight.DARK_FRAME_LUMA
        // No face: too dark to see one, or simply nobody in front of the phone.
        primary ?: return OwnerResult(if (dark) OwnerCheck.TOO_DARK else OwnerCheck.UNCLEAR)
        val usable = OwnerMatching.isUsableFace(
            primary.boundingBox.width() / imageWidth, primary.headEulerAngleY, primary.headEulerAngleX,
        )
        val landmarks = primary.alignmentLandmarks()
        if (!usable || landmarks == null) return OwnerResult(if (dark) OwnerCheck.TOO_DARK else OwnerCheck.UNCLEAR)
        return try {
            check.check(image, landmarks)
        } catch (e: Exception) {
            Log.w(TAG, "Owner check failed", e)
            OwnerResult(OwnerCheck.UNCLEAR)
        }
    }

    private companion object {
        const val TAG = "CameraAnalyzer"
    }
}

/**
 * Per-frame measurements: brightness of the frame and the face, how open the eyes are, and the
 * main face's centre in camera-buffer coordinates (for face-based exposure metering).
 */
data class FrameStats(
    val frameLuma: Float,
    val faceLight: FaceLight? = null,
    val eyesOpen: Float? = null,
    val faceCenterRaw: Pair<Float, Float>? = null,
    val rawWidth: Int = 0,
    val rawHeight: Int = 0,
)

/** Average eye-open probability (0–1), when ML Kit classification is enabled. */
fun Face.eyesOpen(): Float? {
    val left = leftEyeOpenProbability ?: return null
    val right = rightEyeOpenProbability ?: return null
    return (left + right) / 2f
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
