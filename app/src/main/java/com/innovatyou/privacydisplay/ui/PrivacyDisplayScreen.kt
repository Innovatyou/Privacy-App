package com.innovatyou.privacydisplay.ui

import androidx.annotation.StringRes
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material.icons.filled.Warning
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilledTonalButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.innovatyou.privacydisplay.R
import com.innovatyou.privacydisplay.camera.ViewerState
import com.innovatyou.privacydisplay.overlay.PrivacyPolicy
import com.innovatyou.privacydisplay.overlay.ShieldReason
import com.innovatyou.privacydisplay.ui.components.InfoCard
import com.innovatyou.privacydisplay.ui.components.PercentSlider
import com.innovatyou.privacydisplay.ui.components.PrivacyMaskPreview
import com.innovatyou.privacydisplay.ui.components.PrivacyToggle
import com.innovatyou.privacydisplay.ui.components.SettingsCard
import com.innovatyou.privacydisplay.ui.components.SwitchRow
import com.innovatyou.privacydisplay.viewmodel.PrivacyUiState
import kotlin.math.roundToInt

const val STRENGTH_SLIDER_TAG = "strength_slider"
const val VIEWING_AREA_SLIDER_TAG = "viewing_area_slider"

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PrivacyDisplayScreen(
    state: PrivacyUiState,
    actions: PrivacyActions,
    onOpenSettings: () -> Unit,
    modifier: Modifier = Modifier,
    snackbarHostState: SnackbarHostState = remember { SnackbarHostState() },
) {
    val settings = state.settings
    Scaffold(
        modifier = modifier,
        snackbarHost = { SnackbarHost(snackbarHostState) },
        topBar = {
            TopAppBar(
                title = {},
                colors = TopAppBarDefaults.topAppBarColors(containerColor = MaterialTheme.colorScheme.background),
                actions = {
                    IconButton(onClick = onOpenSettings) {
                        Icon(Icons.Filled.Settings, contentDescription = stringResource(R.string.cd_open_settings))
                    }
                },
            )
        },
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            Text(
                stringResource(R.string.privacy_title),
                style = MaterialTheme.typography.displaySmall,
                modifier = Modifier
                    .padding(top = 8.dp)
                    .semantics { heading() },
            )
            Text(
                stringResource(statusText(state)),
                style = MaterialTheme.typography.bodyLarge,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                textAlign = TextAlign.Center,
                modifier = Modifier.semantics { liveRegion = LiveRegionMode.Polite },
            )
            pausedAppText(state)?.let {
                Text(it, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }

            PrivacyToggle(checked = settings.enabled, onCheckedChange = actions.onPrivacyToggle)

            if (!state.permissions.overlay) {
                InfoCard(
                    title = stringResource(R.string.perm_overlay_title),
                    text = stringResource(R.string.perm_overlay_why),
                    icon = Icons.Filled.Warning,
                    action = {
                        FilledTonalButton(onClick = actions.onGrantOverlay) { Text(stringResource(R.string.perm_grant)) }
                    },
                )
            }

            SettingsCard {
                PercentSlider(
                    label = stringResource(R.string.privacy_strength),
                    supportingText = stringResource(R.string.privacy_strength_hint),
                    value = settings.strength,
                    onValueChange = actions.onStrengthChange,
                    testTag = STRENGTH_SLIDER_TAG,
                )
                PercentSlider(
                    label = stringResource(R.string.viewing_area),
                    supportingText = stringResource(R.string.viewing_area_hint),
                    value = settings.clearAreaHeight,
                    onValueChange = actions.onClearAreaHeightChange,
                    valueRange = 0.1f..1f,
                    testTag = VIEWING_AREA_SLIDER_TAG,
                )
            }

            SettingsCard {
                val needsFace = stringResource(R.string.needs_face_detection)
                SwitchRow(
                    title = stringResource(R.string.protect_multiple),
                    subtitle = if (settings.faceDetectionEnabled) stringResource(R.string.protect_multiple_hint) else needsFace,
                    checked = settings.multipleViewerProtection,
                    onCheckedChange = actions.onMultipleViewerProtectionChange,
                    enabled = settings.faceDetectionEnabled,
                )
                SwitchRow(
                    title = stringResource(R.string.face_detection),
                    subtitle = stringResource(R.string.face_detection_hint),
                    checked = settings.faceDetectionEnabled,
                    onCheckedChange = actions.onFaceDetectionChange,
                    enabled = state.permissions.frontCamera,
                )
                SwitchRow(
                    title = stringResource(R.string.blur_on_extra_viewer),
                    subtitle = stringResource(
                        if (state.permissions.windowBlur) R.string.blur_on_extra_viewer_hint else R.string.blur_unsupported
                    ),
                    checked = settings.blurOnExtraViewer,
                    onCheckedChange = actions.onBlurOnExtraViewerChange,
                    enabled = settings.faceDetectionEnabled && settings.multipleViewerProtection,
                )
                SwitchRow(
                    title = stringResource(R.string.auto_enable_unlock),
                    subtitle = stringResource(R.string.auto_enable_unlock_hint),
                    checked = settings.autoEnableOnUnlock,
                    onCheckedChange = actions.onAutoEnableOnUnlockChange,
                )
            }

            Text(
                stringResource(R.string.preview_title),
                style = MaterialTheme.typography.titleMedium,
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(start = 4.dp)
                    .semantics { heading() },
            )
            PrivacyMaskPreview(
                params = PrivacyPolicy.maskParams(settings, state.runtime.viewerState),
                maxOverlayOpacity = state.maxOverlayOpacity,
                contentDescription = stringResource(R.string.preview_hint),
                sampleText = stringResource(R.string.preview_sample_line),
            )

            InfoCard(
                title = stringResource(R.string.disclaimer_title),
                text = buildString {
                    append(stringResource(R.string.disclaimer))
                    if (state.maxOverlayOpacity < 1f) {
                        append("\n\n")
                        append(stringResource(R.string.overlay_limit_note, (state.maxOverlayOpacity * 100).roundToInt()))
                    }
                },
            )
            Spacer(Modifier.height(24.dp))
        }
    }
}

@StringRes
internal fun statusText(state: PrivacyUiState): Int {
    val runtime = state.runtime
    return when {
        !state.permissions.overlay -> R.string.status_permission_needed
        !state.settings.enabled -> R.string.status_off
        runtime.shield == ShieldReason.TEST -> R.string.status_shield_test
        runtime.shield == ShieldReason.EXTRA_VIEWER ->
            if (runtime.shieldBlurs) R.string.status_shield_blurred else R.string.status_shield_darkened
        runtime.shield == ShieldReason.NOBODY_LOOKING -> R.string.status_shield_away
        runtime.viewerState == ViewerState.MULTIPLE_VIEWERS && state.settings.multipleViewerProtection ->
            R.string.status_additional_viewer
        runtime.boosted -> R.string.status_no_viewer
        state.settings.faceDetectionEnabled -> when (runtime.viewerState) {
            ViewerState.BLOCKED_IN_BACKGROUND -> R.string.face_paused_background
            ViewerState.PAUSED_BATTERY -> R.string.face_paused_battery
            ViewerState.UNAVAILABLE -> R.string.face_unavailable
            else -> R.string.status_active
        }
        else -> R.string.status_active
    }
}

@Composable
private fun pausedAppText(state: PrivacyUiState): String? =
    state.runtime.pausedForApp?.takeIf { state.settings.enabled }
        ?.let { stringResource(R.string.status_paused_for_app, it) }
