package com.innovatyou.privacydisplay.ui.components

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.drawscope.drawIntoCanvas
import androidx.compose.ui.graphics.nativeCanvas
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.dp
import com.innovatyou.privacydisplay.overlay.MaskParams
import com.innovatyou.privacydisplay.overlay.MaskRenderer
import com.innovatyou.privacydisplay.overlay.MaskSpec

/**
 * A miniature phone showing sample content under the mask. Uses the same [MaskSpec] and
 * [MaskRenderer] as the real overlay, drawn from a Compose [Canvas] (DrawScope).
 */
@Composable
fun PrivacyMaskPreview(
    params: MaskParams,
    maxOverlayOpacity: Float,
    contentDescription: String,
    sampleText: String,
    modifier: Modifier = Modifier,
) {
    val renderer = remember { MaskRenderer() }
    val alphaScale = 1f / maxOverlayOpacity.coerceAtLeast(0.01f)
    Box(
        modifier = modifier
            .fillMaxWidth(0.55f)
            .aspectRatio(9f / 19.5f)
            .clip(RoundedCornerShape(28.dp))
            .background(MaterialTheme.colorScheme.surfaceContainerHighest)
            .semantics { this.contentDescription = contentDescription },
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(14.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            repeat(SAMPLE_LINES) { index ->
                if (index % 4 == 0) {
                    Text(sampleText, style = MaterialTheme.typography.labelMedium, maxLines = 1)
                } else {
                    Box(
                        Modifier
                            .fillMaxWidth(if (index % 3 == 0) 0.6f else 0.9f)
                            .height(8.dp)
                            .clip(RoundedCornerShape(4.dp))
                            .background(MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.5f))
                    )
                }
            }
        }
        // The overlay window is drawn at the maximum allowed opacity, so the preview applies the
        // same limit (alpha is scaled and then multiplied by maxOverlayOpacity below).
        Canvas(modifier = Modifier.fillMaxSize()) {
            val spec = MaskSpec.compute(params, size.width, size.height)
            drawIntoCanvas { canvas ->
                val saved = canvas.nativeCanvas.saveLayerAlpha(
                    0f, 0f, size.width, size.height, (maxOverlayOpacity.coerceIn(0f, 1f) * 255).toInt()
                )
                renderer.draw(canvas.nativeCanvas, spec, alphaScale)
                canvas.nativeCanvas.restoreToCount(saved)
            }
        }
    }
}

private const val SAMPLE_LINES = 16
