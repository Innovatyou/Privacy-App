package com.innovatyou.privacydisplay.viewmodel

import android.os.SystemClock
import android.util.Log
import androidx.camera.core.ExperimentalGetImage
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.ImageProxy
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.google.android.gms.tasks.Tasks
import com.google.mlkit.vision.common.InputImage
import com.google.mlkit.vision.face.FaceDetection
import com.google.mlkit.vision.face.FaceDetectorOptions
import com.innovatyou.privacydisplay.camera.alignmentLandmarks
import com.innovatyou.privacydisplay.owner.EnrollmentCollector
import com.innovatyou.privacydisplay.owner.FaceEmbedder
import com.innovatyou.privacydisplay.owner.OwnerFaceStore
import com.innovatyou.privacydisplay.service.PrivacyRuntime
import dagger.hilt.android.lifecycle.HiltViewModel
import java.util.concurrent.ExecutorService
import java.util.concurrent.Executors
import javax.inject.Inject
import kotlin.math.abs
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

enum class FaceSetupHint { NO_FACE, ONE_FACE_ONLY, MOVE_CLOSER, HOLD_STILL, FOLLOW_POSE }

data class FaceSetupState(
    val collected: Int = 0,
    val total: Int = 1,
    val pose: EnrollmentCollector.Pose? = EnrollmentCollector.Pose.STRAIGHT,
    val hint: FaceSetupHint = FaceSetupHint.NO_FACE,
    val saving: Boolean = false,
    val done: Boolean = false,
)

/**
 * Records the owner's face print: a few samples looking straight and turned slightly to each
 * side. Camera frames are only analysed in memory; just the resulting numbers are saved,
 * encrypted, by [OwnerFaceStore].
 */
@HiltViewModel
class FaceSetupViewModel @Inject constructor(
    private val embedder: FaceEmbedder,
    private val store: OwnerFaceStore,
    private val runtime: PrivacyRuntime,
) : ViewModel() {

    private val collector = EnrollmentCollector()
    private val _state = MutableStateFlow(FaceSetupState(total = collector.total))
    val state: StateFlow<FaceSetupState> = _state.asStateFlow()

    private val detector = FaceDetection.getClient(
        FaceDetectorOptions.Builder()
            .setPerformanceMode(FaceDetectorOptions.PERFORMANCE_MODE_ACCURATE)
            .setLandmarkMode(FaceDetectorOptions.LANDMARK_MODE_ALL)
            .build()
    )

    /** Thread the camera frames are analysed on. */
    val executor: ExecutorService = Executors.newSingleThreadExecutor()
    private var lastSampleAt = 0L

    val analyzer = ImageAnalysis.Analyzer { image -> analyze(image) }

    init {
        // Let the privacy service release the front camera while setting up.
        runtime.setCameraBusy(true)
    }

    @androidx.annotation.OptIn(markerClass = [ExperimentalGetImage::class])
    private fun analyze(image: ImageProxy) {
        image.use {
            if (collector.isComplete) return
            val mediaImage = image.image ?: return
            val rotation = image.imageInfo.rotationDegrees
            val uprightWidth = if (rotation == 90 || rotation == 270) image.height else image.width
            val faces = try {
                Tasks.await(detector.process(InputImage.fromMediaImage(mediaImage, rotation)))
            } catch (e: Exception) {
                Log.w(TAG, "Face detection failed", e)
                return
            }
            val face = faces.singleOrNull()
            val landmarks = face?.alignmentLandmarks()
            val hint = when {
                faces.isEmpty() -> FaceSetupHint.NO_FACE
                face == null -> FaceSetupHint.ONE_FACE_ONLY
                face.boundingBox.width() < uprightWidth * MIN_FACE_WIDTH -> FaceSetupHint.MOVE_CLOSER
                landmarks == null || abs(face.headEulerAngleX) > MAX_PITCH -> FaceSetupHint.HOLD_STILL
                EnrollmentCollector.poseOf(face.headEulerAngleY) != collector.nextPose -> FaceSetupHint.FOLLOW_POSE
                else -> null
            }
            if (hint != null || face == null || landmarks == null) {
                _state.update { it.copy(hint = hint ?: FaceSetupHint.HOLD_STILL) }
                return
            }
            val now = SystemClock.elapsedRealtime()
            if (now - lastSampleAt < SAMPLE_GAP_MS) return
            lastSampleAt = now

            val frame = image.toBitmap()
            val embedding = try {
                embedder.embed(frame, rotation, landmarks)
            } finally {
                frame.recycle()
            }
            if (collector.offer(face.headEulerAngleY, embedding)) {
                _state.update {
                    it.copy(collected = collector.collected, pose = collector.nextPose, hint = FaceSetupHint.FOLLOW_POSE)
                }
            }
            if (collector.isComplete) save()
        }
    }

    private fun save() {
        _state.update { it.copy(saving = true) }
        viewModelScope.launch {
            store.save(collector.embeddings())
            _state.update { it.copy(saving = false, done = true) }
        }
    }

    override fun onCleared() {
        runtime.setCameraBusy(false)
        detector.close()
        executor.shutdown()
        super.onCleared()
    }

    private companion object {
        const val TAG = "FaceSetup"
        const val MIN_FACE_WIDTH = 0.25f
        const val MAX_PITCH = 20f
        const val SAMPLE_GAP_MS = 500L
    }
}
