package com.innovatyou.privacydisplay.ui

import androidx.annotation.StringRes
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.selection.selectableGroup
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.filled.Face
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilledTonalButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.RadioButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SegmentedButton
import androidx.compose.material3.SegmentedButtonDefaults
import androidx.compose.material3.SingleChoiceSegmentedButtonRow
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.unit.dp
import com.innovatyou.privacydisplay.R
import com.innovatyou.privacydisplay.data.MaskMode
import com.innovatyou.privacydisplay.data.PrivacySettings
import com.innovatyou.privacydisplay.data.ThemeMode
import com.innovatyou.privacydisplay.ui.components.InfoCard
import com.innovatyou.privacydisplay.ui.components.PercentSlider
import com.innovatyou.privacydisplay.ui.components.PermissionRow
import com.innovatyou.privacydisplay.ui.components.SectionHeader
import com.innovatyou.privacydisplay.ui.components.SettingsCard
import com.innovatyou.privacydisplay.ui.components.SwitchRow
import com.innovatyou.privacydisplay.viewmodel.PrivacyUiState

fun maskModeTag(mode: MaskMode) = "mask_mode_${mode.name}"

const val BLUR_EXTRA_VIEWER_TAG = "blur_extra_viewer"
const val TEST_SHIELD_TAG = "test_shield"

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SettingsScreen(
    state: PrivacyUiState,
    actions: PrivacyActions,
    onBack: () -> Unit,
    onOpenExclusions: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val settings = state.settings
    val permissions = state.permissions
    Scaffold(
        modifier = modifier,
        topBar = {
            TopAppBar(
                title = { Text(stringResource(R.string.settings_title)) },
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
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 16.dp),
        ) {
            SectionHeader(stringResource(R.string.section_privacy))
            SettingsCard {
                SwitchRow(
                    title = stringResource(R.string.privacy_mode),
                    checked = settings.enabled,
                    onCheckedChange = actions.onPrivacyToggle,
                )
                PercentSlider(
                    label = stringResource(R.string.privacy_strength),
                    supportingText = stringResource(R.string.privacy_strength_hint),
                    value = settings.strength,
                    onValueChange = actions.onStrengthChange,
                )
                PercentSlider(
                    label = stringResource(R.string.clear_area_width),
                    supportingText = stringResource(R.string.clear_area_width_hint),
                    value = settings.clearAreaWidth,
                    onValueChange = actions.onClearAreaWidthChange,
                    valueRange = PrivacySettings.MIN_CLEAR_AREA..1f,
                )
                PercentSlider(
                    label = stringResource(R.string.clear_area_height),
                    supportingText = stringResource(R.string.clear_area_height_hint),
                    value = settings.clearAreaHeight,
                    onValueChange = actions.onClearAreaHeightChange,
                    valueRange = PrivacySettings.MIN_CLEAR_AREA..1f,
                )
                PercentSlider(
                    label = stringResource(R.string.edge_opacity),
                    supportingText = stringResource(R.string.edge_opacity_hint),
                    value = settings.edgeOpacity,
                    onValueChange = actions.onEdgeOpacityChange,
                )
                PercentSlider(
                    label = stringResource(R.string.edge_softness),
                    supportingText = stringResource(R.string.edge_softness_hint),
                    value = settings.gradientWidth,
                    onValueChange = actions.onGradientWidthChange,
                    valueRange = 0f..PrivacySettings.MAX_GRADIENT_WIDTH,
                )
            }

            SectionHeader(stringResource(R.string.section_mask))
            SettingsCard(modifier = Modifier.selectableGroup()) {
                MaskMode.entries.forEach { mode ->
                    MaskModeRow(
                        mode = mode,
                        selected = settings.maskMode == mode,
                        onSelect = { actions.onMaskModeChange(mode) },
                    )
                }
            }

            SectionHeader(stringResource(R.string.section_detection))
            SettingsCard {
                SwitchRow(
                    title = stringResource(R.string.face_detection),
                    subtitle = stringResource(R.string.face_detection_hint),
                    checked = settings.faceDetectionEnabled,
                    onCheckedChange = actions.onFaceDetectionChange,
                    enabled = permissions.frontCamera,
                )
                val needsFace = stringResource(R.string.needs_face_detection)
                SwitchRow(
                    title = stringResource(R.string.protect_multiple),
                    subtitle = if (settings.faceDetectionEnabled) stringResource(R.string.protect_multiple_hint) else needsFace,
                    checked = settings.multipleViewerProtection,
                    onCheckedChange = actions.onMultipleViewerProtectionChange,
                    enabled = settings.faceDetectionEnabled,
                )
                SwitchRow(
                    title = stringResource(R.string.strongest_mask),
                    subtitle = stringResource(R.string.strongest_mask_hint),
                    checked = settings.strongestMaskOnMultipleViewers,
                    onCheckedChange = actions.onStrongestMaskChange,
                    enabled = settings.multipleViewerProtection,
                )
            }

            SectionHeader(stringResource(R.string.section_shield))
            ViewerShieldCard(state, actions)
            Spacer(Modifier.height(12.dp))
            InfoCard(
                title = stringResource(R.string.shield_explainer_title),
                text = stringResource(R.string.shield_explainer),
                icon = Icons.Filled.Face,
            )

            SectionHeader(stringResource(R.string.section_automation))
            SettingsCard {
                SwitchRow(
                    title = stringResource(R.string.auto_enable_unlock),
                    subtitle = stringResource(R.string.auto_enable_unlock_hint),
                    checked = settings.autoEnableOnUnlock,
                    onCheckedChange = actions.onAutoEnableOnUnlockChange,
                )
                NavigationRow(
                    title = stringResource(R.string.app_exclusions),
                    subtitle = if (permissions.usageAccess || settings.excludedApps.isEmpty()) {
                        stringResource(R.string.app_exclusions_summary, settings.excludedApps.size)
                    } else {
                        stringResource(R.string.usage_access_needed)
                    },
                    onClick = onOpenExclusions,
                )
            }

            SectionHeader(stringResource(R.string.section_battery))
            SettingsCard {
                SwitchRow(
                    title = stringResource(R.string.battery_saver),
                    subtitle = stringResource(R.string.battery_saver_hint),
                    checked = settings.batterySaver,
                    onCheckedChange = actions.onBatterySaverChange,
                )
            }

            SectionHeader(stringResource(R.string.section_appearance))
            SettingsCard {
                Text(
                    stringResource(R.string.dark_mode),
                    style = MaterialTheme.typography.bodyLarge,
                    modifier = Modifier.padding(horizontal = 20.dp, vertical = 8.dp),
                )
                SingleChoiceSegmentedButtonRow(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 20.dp, vertical = 8.dp),
                ) {
                    val modes = ThemeMode.entries
                    modes.forEachIndexed { index, mode ->
                        SegmentedButton(
                            selected = settings.themeMode == mode,
                            onClick = { actions.onThemeModeChange(mode) },
                            shape = SegmentedButtonDefaults.itemShape(index = index, count = modes.size),
                        ) {
                            Text(stringResource(themeLabel(mode)))
                        }
                    }
                }
            }

            SectionHeader(stringResource(R.string.section_permissions))
            SettingsCard {
                PermissionRow(
                    title = stringResource(R.string.perm_overlay_title),
                    rationale = stringResource(R.string.perm_overlay_why),
                    granted = permissions.overlay,
                    onGrant = actions.onGrantOverlay,
                )
                if (permissions.frontCamera) {
                    PermissionRow(
                        title = stringResource(R.string.perm_camera_title),
                        rationale = stringResource(R.string.perm_camera_why),
                        granted = permissions.camera,
                        onGrant = actions.onGrantCamera,
                    )
                }
                PermissionRow(
                    title = stringResource(R.string.perm_notifications_title),
                    rationale = stringResource(R.string.perm_notifications_why),
                    granted = permissions.notifications,
                    onGrant = actions.onGrantNotifications,
                )
                PermissionRow(
                    title = stringResource(R.string.perm_usage_title),
                    rationale = stringResource(R.string.perm_usage_why),
                    granted = permissions.usageAccess,
                    onGrant = actions.onGrantUsageAccess,
                )
            }

            SectionHeader(stringResource(R.string.section_about))
            InfoCard(
                title = stringResource(R.string.about_hardware_title),
                text = stringResource(R.string.about_hardware),
                icon = Icons.Filled.Lock,
            )
            Spacer(Modifier.height(12.dp))
            InfoCard(
                title = stringResource(R.string.about_data_title),
                text = stringResource(R.string.about_data),
                icon = Icons.Filled.Face,
            )
            Spacer(Modifier.height(32.dp))
        }
    }
}

@Composable
private fun ViewerShieldCard(state: PrivacyUiState, actions: PrivacyActions) {
    val settings = state.settings
    val detectionReady = settings.faceDetectionEnabled && settings.multipleViewerProtection
    SettingsCard {
        SwitchRow(
            title = stringResource(R.string.blur_on_extra_viewer),
            subtitle = stringResource(R.string.blur_on_extra_viewer_hint),
            checked = settings.blurOnExtraViewer,
            onCheckedChange = actions.onBlurOnExtraViewerChange,
            enabled = detectionReady,
            testTag = BLUR_EXTRA_VIEWER_TAG,
        )
        SwitchRow(
            title = stringResource(R.string.blur_when_away),
            subtitle = stringResource(R.string.blur_when_away_hint),
            checked = settings.blurWhenAway,
            onCheckedChange = actions.onBlurWhenAwayChange,
            enabled = settings.faceDetectionEnabled,
        )
        PercentSlider(
            label = stringResource(R.string.blur_strength),
            value = settings.blurStrength,
            onValueChange = actions.onBlurStrengthChange,
            enabled = state.permissions.windowBlur,
        )
        if (!state.permissions.windowBlur) {
            Text(
                stringResource(R.string.blur_unsupported),
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(horizontal = 20.dp, vertical = 4.dp),
            )
        }
        Column(modifier = Modifier.padding(horizontal = 20.dp, vertical = 8.dp)) {
            FilledTonalButton(
                onClick = actions.onTestShield,
                enabled = settings.enabled,
                modifier = Modifier
                    .heightIn(min = 48.dp)
                    .testTag(TEST_SHIELD_TAG),
            ) {
                Text(stringResource(R.string.test_shield))
            }
            Text(
                stringResource(R.string.test_shield_hint),
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(top = 4.dp),
            )
        }
    }
}

@Composable
private fun MaskModeRow(mode: MaskMode, selected: Boolean, onSelect: () -> Unit) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 64.dp)
            .selectable(selected = selected, role = Role.RadioButton, onClick = onSelect)
            .testTag(maskModeTag(mode))
            .padding(horizontal = 12.dp, vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        RadioButton(selected = selected, onClick = null, modifier = Modifier.padding(horizontal = 8.dp))
        Column(modifier = Modifier.weight(1f)) {
            Text(stringResource(maskTitle(mode)), style = MaterialTheme.typography.bodyLarge)
            Text(
                stringResource(maskDescription(mode)),
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@Composable
private fun NavigationRow(title: String, subtitle: String, onClick: () -> Unit) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 64.dp)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 20.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(modifier = Modifier.weight(1f)) {
            Text(title, style = MaterialTheme.typography.bodyLarge)
            Text(subtitle, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, contentDescription = null)
    }
}

@StringRes
fun maskTitle(mode: MaskMode): Int = when (mode) {
    MaskMode.BLACK -> R.string.mask_black
    MaskMode.DARK_EDGES -> R.string.mask_dark_edges
    MaskMode.NARROW_WINDOW -> R.string.mask_narrow
    MaskMode.GRADIENT -> R.string.mask_gradient
    MaskMode.FROSTED -> R.string.mask_frosted
    MaskMode.CUSTOM -> R.string.mask_custom
}

@StringRes
private fun maskDescription(mode: MaskMode): Int = when (mode) {
    MaskMode.BLACK -> R.string.mask_black_desc
    MaskMode.DARK_EDGES -> R.string.mask_dark_edges_desc
    MaskMode.NARROW_WINDOW -> R.string.mask_narrow_desc
    MaskMode.GRADIENT -> R.string.mask_gradient_desc
    MaskMode.FROSTED -> R.string.mask_frosted_desc
    MaskMode.CUSTOM -> R.string.mask_custom_desc
}

@StringRes
private fun themeLabel(mode: ThemeMode): Int = when (mode) {
    ThemeMode.SYSTEM -> R.string.theme_system
    ThemeMode.LIGHT -> R.string.theme_light
    ThemeMode.DARK -> R.string.theme_dark
}
