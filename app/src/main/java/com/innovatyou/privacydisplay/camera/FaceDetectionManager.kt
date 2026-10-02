package com.innovatyou.privacydisplay.camera

import android.content.Context
import android.os.SystemClock
import android.util.Log
import android.util.Size
import androidx.camera.core.CameraSelector
import androidx.camera.core.CameraState
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.resolutionselector.ResolutionSelector
import androidx.camera.core.resolutionselector.ResolutionStrategy
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.core.content.ContextCompat
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleOwner
import androidx.lifecycle.LifecycleRegistry
import com.google.mlkit.vision.face.FaceDetection
import com.google.mlkit.vision.face.FaceDetectorOptions
import androidx.camera.core.Camera
import com.innovatyou.privacydisplay.owner.BlinkDetector
import com.innovatyou.privacydisplay.owner.FaceEmbedder
import com.innovatyou.privacydisplay.owner.LowLight
import com.innovatyou.privacydisplay.owner.OwnerCheck
import com.innovatyou.privacydisplay.owner.OwnerMatching
import com.innovatyou.privacydisplay.owner.OwnerVerifier
import com.innovatyou.privacydisplay.util.OrientationManager
import dagger.hilt.android.qualifiers.ApplicationContext
import java.util.concurrent.Executors
import javax.inject.Inject
import javax.inject.Singleton
import kotlin.coroutines.resume
import kotlin.coroutines.resumeWithException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.channels.awaitClose
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.callbackFlow
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.flowOn
import kotlinx.coroutines.launch
import kotlinx.coroutines.suspendCancellableCoroutine

/**
 * Estimates who is looking at the screen with the front camera and ML Kit face detection.
 *
 * The camera only runs while [viewerReports] is being collected. Only an analysis stream is bound
 * (no preview, no capture), at a low resolution, and frames are dropped unless the analyzer is
 * ready for the next one. Detection runs on-device with the bundled ML Kit model.
 */
@Singleton
class FaceDetectionManager @Inject constructor(
    @ApplicationContext private val context: Context,
    private val orientationManager: OrientationManager,
    private val embedder: FaceEmbedder,
) {
    /**
     * @param ownerFacePrint when not null, the main face is also compared with the owner's face
     *   print and [ViewerReport.owner] is filled in.
     */
    fun viewerReports(
        minIntervalMs: Long,
        ownerFacePrint: List<FloatArray>? = null,
        ownerIntervalMs: Long = DEFAULT_OWNER_INTERVAL_MS,
    ): Flow<ViewerReport> = callbackFlow {
        trySend(ViewerReport(ViewerState.STARTING))

        val provider = try {
            awaitCameraProvider()
        } catch (e: Exception) {
            Log.w(TAG, "Camera provider unavailable", e)
            null
        }
        val hasFrontCamera = try {
            provider?.hasCamera(CameraSelector.DEFAULT_FRONT_CAMERA) == true
        } catch (e: Exception) {
            false
        }
        if (provider == null || !hasFrontCamera) {
            trySend(ViewerReport(ViewerState.UNAVAILABLE))
            awaitClose { }
            return@callbackFlow
        }

        val recognize = !ownerFacePrint.isNullOrEmpty()
        val detector = FaceDetection.getClient(if (recognize) RECOGNITION_OPTIONS else DETECTOR_OPTIONS)
        val executor = Executors.newSingleThreadExecutor()
        val smoother = ViewerStateSmoother()
        val verifier = OwnerVerifier()
        val blinks = BlinkDetector()
        var lastSide: ViewerSide? = null
        var lastFaceLuma: Float? = null
        var camera: Camera? = null
        var exposureIndex = 0
        val ownerCheck = if (recognize) {
            CameraAnalyzer.OwnerCheckFn { image, landmarks ->
                val frame = image.toBitmap()
                try {
                    val sample = embedder.embedIfBright(
                        frame, image.imageInfo.rotationDegrees, landmarks, LowLight.MIN_FACE_LUMA,
                    )
                    val check = sample.embedding?.let { OwnerMatching.check(it, ownerFacePrint.orEmpty()) }
                        ?: OwnerCheck.TOO_DARK
                    CameraAnalyzer.OwnerResult(check, sample.luma)
                } finally {
                    frame.recycle()
                }
            }
        } else {
            null
        }
        // Called on the single analysis thread; trySend is thread-safe.
        val analyzer = CameraAnalyzer(
            detector = detector,
            minIntervalMs = minIntervalMs,
            ownerCheck = ownerCheck,
            ownerIntervalMs = ownerIntervalMs,
            onResult = { observation, owner, stats ->
                val now = SystemClock.elapsedRealtime()
                observation.extraViewerSide?.let { lastSide = it }
                stats.faceLuma?.let { lastFaceLuma = it }
                if (observation.faceCount == 0) lastFaceLuma = null
                val smoothed = smoother.update(observation, now)
                // "No face" in a dim scene only means the camera cannot see, not that nobody looks.
                val state = if (smoothed == ViewerState.NO_FACE && stats.frameLuma < LowLight.DIM_FRAME_LUMA) {
                    ViewerState.TOO_DARK
                } else {
                    smoothed
                }
                val decision = if (owner != null) verifier.update(owner) else verifier.decision
                blinks.update(stats.eyesOpen, now)

                // Low light: ask the camera for more exposure, and back to normal when bright.
                camera?.let { cam ->
                    val exposure = cam.cameraInfo.exposureState
                    if (exposure.isExposureCompensationSupported) {
                        val target = LowLight.exposureTarget(stats.frameLuma, exposure.exposureCompensationRange.upper)
                        if (target != null && target != exposureIndex) {
                            exposureIndex = target
                            cam.cameraControl.setExposureCompensationIndex(target)
                        }
                    }
                }

                trySend(
                    ViewerReport(
                        state = state,
                        extraViewerSide = lastSide.takeIf { state == ViewerState.MULTIPLE_VIEWERS },
                        owner = decision,
                        recentBlink = blinks.blinkedWithin(BlinkDetector.WINDOW_MS, now),
                        lowLight = LowLight.isDim(stats.frameLuma, lastFaceLuma),
                    )
                )
            },
        )
        val analysis = ImageAnalysis.Builder()
            .setResolutionSelector(RESOLUTION_SELECTOR)
            .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
            .setTargetRotation(orientationManager.current().rotation)
            .build()
        analysis.setAnalyzer(executor, analyzer)

        val owner = CameraLifecycleOwner()
        try {
            val bound = provider.bindToLifecycle(owner, CameraSelector.DEFAULT_FRONT_CAMERA, analysis)
            camera = bound
            bound.cameraInfo.cameraState.observe(owner) { state ->
                // Another app took the camera, or it failed: do not treat this as "no face".
                if (state.error != null || state.type == CameraState.Type.PENDING_OPEN) {
                    trySend(ViewerReport(ViewerState.UNAVAILABLE))
                }
            }
            owner.start()
        } catch (e: Exception) {
            Log.w(TAG, "Could not start the front camera", e)
            trySend(ViewerReport(ViewerState.UNAVAILABLE))
        }

        val rotationJob = launch {
            orientationManager.displayChanges().collect { analysis.targetRotation = it.rotation }
        }

        awaitClose {
            rotationJob.cancel()
            provider.unbind(analysis)
            analysis.clearAnalyzer()
            owner.destroy()
            detector.close()
            executor.shutdown()
        }
    }
        .flowOn(Dispatchers.Main.immediate) // CameraX binding must happen on the main thread.
        .distinctUntilChanged()

    private suspend fun awaitCameraProvider(): ProcessCameraProvider =
        suspendCancellableCoroutine { cont ->
            val future = ProcessCameraProvider.getInstance(context)
            future.addListener({
                try {
                    cont.resume(future.get())
                } catch (e: Exception) {
                    cont.resumeWithException(e)
                }
            }, ContextCompat.getMainExecutor(context))
        }

    /** Lifecycle for the camera, independent of any activity, so it can run from the service. */
    private class CameraLifecycleOwner : LifecycleOwner {
        private val registry = LifecycleRegistry(this).apply { currentState = Lifecycle.State.CREATED }
        override val lifecycle: Lifecycle get() = registry

        fun start() {
            registry.currentState = Lifecycle.State.RESUMED
        }

        fun destroy() {
            registry.currentState = Lifecycle.State.DESTROYED
        }
    }

    private companion object {
        const val TAG = "FaceDetection"

        val DETECTOR_OPTIONS: FaceDetectorOptions = FaceDetectorOptions.Builder()
            .setPerformanceMode(FaceDetectorOptions.PERFORMANCE_MODE_FAST)
            .setLandmarkMode(FaceDetectorOptions.LANDMARK_MODE_NONE)
            .setClassificationMode(FaceDetectorOptions.CLASSIFICATION_MODE_NONE)
            .setContourMode(FaceDetectorOptions.CONTOUR_MODE_NONE)
            // Small faces too, so people further away (looking over a shoulder) are counted.
            .setMinFaceSize(0.1f)
            .build()

        const val DEFAULT_OWNER_INTERVAL_MS = 1_000L

        /**
         * Same as above plus landmarks, which owner recognition needs to align the face, and eye
         * classification for the blink (liveness) check.
         */
        val RECOGNITION_OPTIONS: FaceDetectorOptions = FaceDetectorOptions.Builder()
            .setPerformanceMode(FaceDetectorOptions.PERFORMANCE_MODE_FAST)
            .setLandmarkMode(FaceDetectorOptions.LANDMARK_MODE_ALL)
            .setClassificationMode(FaceDetectorOptions.CLASSIFICATION_MODE_ALL)
            .setContourMode(FaceDetectorOptions.CONTOUR_MODE_NONE)
            .setMinFaceSize(0.1f)
            .build()

        val RESOLUTION_SELECTOR: ResolutionSelector = ResolutionSelector.Builder()
            .setResolutionStrategy(
                ResolutionStrategy(
                    Size(640, 480),
                    ResolutionStrategy.FALLBACK_RULE_CLOSEST_LOWER_THEN_HIGHER,
                )
            )
            .build()
    }
}
