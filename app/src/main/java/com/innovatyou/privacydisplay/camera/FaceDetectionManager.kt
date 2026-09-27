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
) {
    fun viewerReports(minIntervalMs: Long): Flow<ViewerReport> = callbackFlow {
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

        val detector = FaceDetection.getClient(DETECTOR_OPTIONS)
        val executor = Executors.newSingleThreadExecutor()
        val smoother = ViewerStateSmoother()
        var lastSide: ViewerSide? = null
        val analyzer = CameraAnalyzer(
            detector = detector,
            minIntervalMs = minIntervalMs,
            onResult = { observation ->
                observation.extraViewerSide?.let { lastSide = it }
                val state = smoother.update(observation, SystemClock.elapsedRealtime())
                trySend(ViewerReport(state, lastSide.takeIf { state == ViewerState.MULTIPLE_VIEWERS }))
            },
            onError = { Log.w(TAG, "Face detection failed", it) },
        )
        val analysis = ImageAnalysis.Builder()
            .setResolutionSelector(RESOLUTION_SELECTOR)
            .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
            .setTargetRotation(orientationManager.current().rotation)
            .build()
        analysis.setAnalyzer(executor, analyzer)

        val owner = CameraLifecycleOwner()
        try {
            val camera = provider.bindToLifecycle(owner, CameraSelector.DEFAULT_FRONT_CAMERA, analysis)
            camera.cameraInfo.cameraState.observe(owner) { state ->
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
