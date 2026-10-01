package com.innovatyou.privacydisplay.ui

import androidx.compose.runtime.Immutable
import com.innovatyou.privacydisplay.data.MaskMode
import com.innovatyou.privacydisplay.data.ThemeMode

/** Callbacks from the screens. Defaults are no-ops so screens can be previewed and tested alone. */
@Immutable
data class PrivacyActions(
    val onPrivacyToggle: (Boolean) -> Unit = {},
    val onStrengthChange: (Float) -> Unit = {},
    val onClearAreaWidthChange: (Float) -> Unit = {},
    val onClearAreaHeightChange: (Float) -> Unit = {},
    val onEdgeOpacityChange: (Float) -> Unit = {},
    val onGradientWidthChange: (Float) -> Unit = {},
    val onMaskModeChange: (MaskMode) -> Unit = {},
    val onFaceDetectionChange: (Boolean) -> Unit = {},
    val onMultipleViewerProtectionChange: (Boolean) -> Unit = {},
    val onStrongestMaskChange: (Boolean) -> Unit = {},
    val onAutoEnableOnUnlockChange: (Boolean) -> Unit = {},
    val onBatterySaverChange: (Boolean) -> Unit = {},
    val onThemeModeChange: (ThemeMode) -> Unit = {},
    val onBlurOnExtraViewerChange: (Boolean) -> Unit = {},
    val onBlurWhenAwayChange: (Boolean) -> Unit = {},
    val onBlurStrengthChange: (Float) -> Unit = {},
    val onTestShield: () -> Unit = {},
    val onStartSharing: () -> Unit = {},
    val onStopSharing: () -> Unit = {},
    val onShareMinutesChange: (Int) -> Unit = {},
    val onOwnerProtectionChange: (Boolean) -> Unit = {},
    val onSetUpFace: () -> Unit = {},
    val onDeleteFace: () -> Unit = {},
    val onOpenSecuritySettings: () -> Unit = {},
    val onGrantOverlay: () -> Unit = {},
    val onGrantCamera: () -> Unit = {},
    val onGrantNotifications: () -> Unit = {},
    val onGrantUsageAccess: () -> Unit = {},
)
