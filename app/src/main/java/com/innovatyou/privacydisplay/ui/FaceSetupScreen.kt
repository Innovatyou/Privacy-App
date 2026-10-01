package com.innovatyou.privacydisplay.ui

import android.util.Size
import androidx.camera.core.CameraSelector
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.Preview
import androidx.camera.core.resolutionselector.ResolutionSelector
import androidx.camera.core.resolutionselector.ResolutionStrategy
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.Button
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.innovatyou.privacydisplay.R
import com.innovatyou.privacydisplay.owner.EnrollmentCollector
import com.innovatyou.privacydisplay.viewmodel.FaceSetupHint
import com.innovatyou.privacydisplay.viewmodel.FaceSetupState
import com.innovatyou.privacydisplay.viewmodel.FaceSetupViewModel

/** Records the owner's face print with the front camera. */
@Composable
fun FaceSetupRoute(onDone: () -> Unit, onBack: () -> Unit) {
    val viewModel: FaceSetupViewModel = hiltViewModel()
    val state by viewModel.state.collectAsStateWithLifecycle()
    FaceSetupScreen(
        state = state,
        onDone = onDone,
        onBack = onBack,
        camera = { modifier -> FrontCameraPreview(viewModel, modifier) },
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun FaceSetupScreen(
    state: FaceSetupState,
    onDone: () -> Unit,
    onBack: () -> Unit,
    camera: @Composable (Modifier) -> Unit,
) {
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(stringResource(R.string.face_setup_title)) },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = MaterialTheme.colorScheme.background),
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = stringResource(R.string.cd_back))
                    }
                },
            )
        },
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(20.dp),
        ) {
            if (state.done) {
                Text(
                    stringResource(R.string.face_setup_done),
                    style = MaterialTheme.typography.titleLarge,
                    textAlign = TextAlign.Center,
                )
                Text(
                    stringResource(R.string.face_setup_done_text),
                    style = MaterialTheme.typography.bodyLarge,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    textAlign = TextAlign.Center,
                )
                Button(onClick = onDone, modifier = Modifier.heightIn(min = 52.dp)) {
                    Text(stringResource(R.string.done))
                }
            } else {
                camera(
                    Modifier
                        .size(260.dp)
                        .clip(CircleShape)
                        .border(4.dp, MaterialTheme.colorScheme.primary, CircleShape)
                )
                LinearProgressIndicator(
                    progress = { state.collected / state.total.toFloat() },
                    modifier = Modifier.fillMaxWidth(),
                )
                Text(
                    stringResource(instruction(state)),
                    style = MaterialTheme.typography.titleMedium,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.semantics { liveRegion = LiveRegionMode.Polite },
                )
                Text(
                    stringResource(R.string.face_setup_privacy),
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    textAlign = TextAlign.Center,
                )
            }
        }
    }
}

private fun instruction(state: FaceSetupState): Int = when {
    state.saving -> R.string.face_setup_saving
    state.hint == FaceSetupHint.NO_FACE -> R.string.face_setup_no_face
    state.hint == FaceSetupHint.ONE_FACE_ONLY -> R.string.face_setup_one_face
    state.hint == FaceSetupHint.MOVE_CLOSER -> R.string.face_setup_closer
    state.hint == FaceSetupHint.HOLD_STILL -> R.string.face_setup_hold_still
    state.pose == EnrollmentCollector.Pose.LEFT -> R.string.face_setup_turn_one_side
    state.pose == EnrollmentCollector.Pose.RIGHT -> R.string.face_setup_turn_other_side
    else -> R.string.face_setup_look_straight
}

@Composable
private fun FrontCameraPreview(viewModel: FaceSetupViewModel, modifier: Modifier) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val previewView = remember { PreviewView(context).apply { scaleType = PreviewView.ScaleType.FILL_CENTER } }
    AndroidView(factory = { previewView }, modifier = modifier)

    DisposableEffect(lifecycleOwner) {
        val preview = Preview.Builder().build().also { it.setSurfaceProvider(previewView.surfaceProvider) }
        val analysis = ImageAnalysis.Builder()
            .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
            .setResolutionSelector(
                ResolutionSelector.Builder()
                    .setResolutionStrategy(
                        ResolutionStrategy(Size(640, 480), ResolutionStrategy.FALLBACK_RULE_CLOSEST_LOWER_THEN_HIGHER)
                    )
                    .build()
            )
            .build()
            .also { it.setAnalyzer(viewModel.executor, viewModel.analyzer) }
        val future = ProcessCameraProvider.getInstance(context)
        var provider: ProcessCameraProvider? = null
        future.addListener({
            provider = future.get().also {
                runCatching {
                    it.bindToLifecycle(lifecycleOwner, CameraSelector.DEFAULT_FRONT_CAMERA, preview, analysis)
                }
            }
        }, ContextCompat.getMainExecutor(context))
        onDispose {
            provider?.unbind(preview, analysis)
            analysis.clearAnalyzer()
        }
    }
}
