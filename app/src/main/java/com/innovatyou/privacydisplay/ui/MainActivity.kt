package com.innovatyou.privacydisplay.ui

import android.Manifest
import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.material3.SnackbarHostState
import androidx.compose.ui.platform.LocalContext
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import com.innovatyou.privacydisplay.R
import com.innovatyou.privacydisplay.service.PrivacyController
import com.innovatyou.privacydisplay.ui.theme.PrivacyDisplayTheme
import com.innovatyou.privacydisplay.util.PermissionIntents
import com.innovatyou.privacydisplay.viewmodel.PrivacyEvent
import com.innovatyou.privacydisplay.viewmodel.PrivacyViewModel
import dagger.hilt.android.AndroidEntryPoint
import javax.inject.Inject

@AndroidEntryPoint
class MainActivity : ComponentActivity() {

    @Inject lateinit var controller: PrivacyController

    override fun onCreate(savedInstanceState: Bundle?) {
        enableEdgeToEdge()
        super.onCreate(savedInstanceState)
        setContent {
            val viewModel: PrivacyViewModel = hiltViewModel()
            val state by viewModel.uiState.collectAsStateWithLifecycle()
            PrivacyDisplayTheme(themeMode = state.settings.themeMode) {
                PrivacyApp(viewModel)
            }
        }
    }

    override fun onResume() {
        super.onResume()
        // While the app is visible Android allows the service to start and to use the camera.
        controller.onAppForeground()
    }
}

private object Routes {
    const val HOME = "home"
    const val SETTINGS = "settings"
    const val EXCLUSIONS = "exclusions"
}

@Composable
private fun PrivacyApp(viewModel: PrivacyViewModel) {
    val context = LocalContext.current
    val navController = rememberNavController()
    val state by viewModel.uiState.collectAsStateWithLifecycle()
    val snackbarHostState = remember { SnackbarHostState() }

    val cameraPermission = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) {
        viewModel.onCameraPermissionResult(it)
    }
    val notificationPermission = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) {
        viewModel.refreshPermissions()
    }

    // Permissions granted in system settings are picked up when the user comes back.
    LifecycleEventEffect(Lifecycle.Event.ON_RESUME) { viewModel.refreshPermissions() }

    val startFailed = context.getString(R.string.service_start_failed)
    val cameraDenied = context.getString(R.string.camera_denied)
    LaunchedEffect(viewModel) {
        viewModel.events.collect { event ->
            when (event) {
                PrivacyEvent.RequestOverlayPermission ->
                    context.startActivity(PermissionIntents.overlaySettings(context))
                PrivacyEvent.RequestCameraPermission ->
                    cameraPermission.launch(Manifest.permission.CAMERA)
                PrivacyEvent.RequestNotificationPermission ->
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                        notificationPermission.launch(Manifest.permission.POST_NOTIFICATIONS)
                    }
                PrivacyEvent.ServiceStartFailed -> snackbarHostState.showSnackbar(startFailed)
                PrivacyEvent.CameraPermissionDenied -> snackbarHostState.showSnackbar(cameraDenied)
            }
        }
    }

    val actions = remember(viewModel) {
        PrivacyActions(
            onPrivacyToggle = viewModel::setPrivacyEnabled,
            onStrengthChange = viewModel::setStrength,
            onClearAreaWidthChange = viewModel::setClearAreaWidth,
            onClearAreaHeightChange = viewModel::setClearAreaHeight,
            onEdgeOpacityChange = viewModel::setEdgeOpacity,
            onGradientWidthChange = viewModel::setGradientWidth,
            onMaskModeChange = viewModel::setMaskMode,
            onFaceDetectionChange = viewModel::setFaceDetection,
            onMultipleViewerProtectionChange = viewModel::setMultipleViewerProtection,
            onStrongestMaskChange = viewModel::setStrongestMaskOnMultipleViewers,
            onAutoEnableOnUnlockChange = viewModel::setAutoEnableOnUnlock,
            onBatterySaverChange = viewModel::setBatterySaver,
            onThemeModeChange = viewModel::setThemeMode,
            onBlurOnExtraViewerChange = viewModel::setBlurOnExtraViewer,
            onBlurWhenAwayChange = viewModel::setBlurWhenAway,
            onBlurStrengthChange = viewModel::setBlurStrength,
            onTestShield = viewModel::testShield,
            onGrantOverlay = { context.startActivity(PermissionIntents.overlaySettings(context)) },
            onGrantCamera = { cameraPermission.launch(Manifest.permission.CAMERA) },
            onGrantNotifications = {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                    notificationPermission.launch(Manifest.permission.POST_NOTIFICATIONS)
                } else {
                    context.startActivity(PermissionIntents.appDetails(context))
                }
            },
            onGrantUsageAccess = { context.startActivity(PermissionIntents.usageAccessSettings()) },
        )
    }

    NavHost(navController = navController, startDestination = Routes.HOME) {
        composable(Routes.HOME) {
            PrivacyDisplayScreen(
                state = state,
                actions = actions,
                onOpenSettings = { navController.navigate(Routes.SETTINGS) },
                snackbarHostState = snackbarHostState,
            )
        }
        composable(Routes.SETTINGS) {
            SettingsScreen(
                state = state,
                actions = actions,
                onBack = { navController.popBackStack() },
                onOpenExclusions = { navController.navigate(Routes.EXCLUSIONS) },
            )
        }
        composable(Routes.EXCLUSIONS) {
            val apps by viewModel.apps.collectAsStateWithLifecycle()
            LaunchedEffect(Unit) { viewModel.loadApps() }
            AppExclusionsScreen(
                apps = apps,
                excluded = state.settings.excludedApps,
                usageAccessGranted = state.permissions.usageAccess,
                onToggle = viewModel::toggleExcludedApp,
                onGrantUsageAccess = actions.onGrantUsageAccess,
                onBack = { navController.popBackStack() },
            )
        }
    }
}
