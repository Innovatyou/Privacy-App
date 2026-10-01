package com.innovatyou.privacydisplay.util

import android.Manifest
import android.app.AppOpsManager
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.hardware.input.InputManager
import android.net.Uri
import android.os.Build
import android.os.Process
import android.provider.Settings
import androidx.core.content.ContextCompat
import com.innovatyou.privacydisplay.owner.OwnerAuthenticator
import dagger.hilt.android.qualifiers.ApplicationContext
import javax.inject.Inject
import javax.inject.Singleton

/** Snapshot of the permissions and device capabilities the app depends on. */
data class PermissionState(
    val overlay: Boolean = false,
    val camera: Boolean = false,
    val notifications: Boolean = false,
    val usageAccess: Boolean = false,
    val frontCamera: Boolean = true,
    /** System blur (Android 12+ cross-window blur) is available for the viewer shield. */
    val windowBlur: Boolean = false,
    /** The phone has a screen lock (needed to unlock owner protection with a fingerprint/PIN). */
    val screenLock: Boolean = false,
)

interface PermissionManager {
    fun canDrawOverlays(): Boolean
    fun hasCameraPermission(): Boolean
    fun hasNotificationPermission(): Boolean
    fun hasUsageAccess(): Boolean
    fun hasFrontCamera(): Boolean
    fun supportsWindowBlur(): Boolean
    fun hasScreenLock(): Boolean

    /**
     * The most opaque an overlay may be while still letting touches through to the apps below.
     * Android 12+ blocks touches under untrusted overlays that are more opaque than this (0.8).
     */
    fun maxOverlayOpacity(): Float

    fun snapshot() = PermissionState(
        overlay = canDrawOverlays(),
        camera = hasCameraPermission(),
        notifications = hasNotificationPermission(),
        usageAccess = hasUsageAccess(),
        frontCamera = hasFrontCamera(),
        windowBlur = supportsWindowBlur(),
        screenLock = hasScreenLock(),
    )
}

@Singleton
class AndroidPermissionManager @Inject constructor(
    @ApplicationContext private val context: Context,
    private val blurSupport: BlurSupport,
) : PermissionManager {

    override fun canDrawOverlays(): Boolean = Settings.canDrawOverlays(context)

    override fun hasCameraPermission(): Boolean = granted(Manifest.permission.CAMERA)

    override fun hasNotificationPermission(): Boolean =
        Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU ||
            granted(Manifest.permission.POST_NOTIFICATIONS)

    override fun hasUsageAccess(): Boolean {
        val appOps = context.getSystemService(AppOpsManager::class.java) ?: return false
        val mode = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            appOps.unsafeCheckOpNoThrow(
                AppOpsManager.OPSTR_GET_USAGE_STATS, Process.myUid(), context.packageName
            )
        } else {
            @Suppress("DEPRECATION")
            appOps.checkOpNoThrow(
                AppOpsManager.OPSTR_GET_USAGE_STATS, Process.myUid(), context.packageName
            )
        }
        return if (mode == AppOpsManager.MODE_DEFAULT) {
            granted(Manifest.permission.PACKAGE_USAGE_STATS)
        } else {
            mode == AppOpsManager.MODE_ALLOWED
        }
    }

    override fun hasFrontCamera(): Boolean =
        context.packageManager.hasSystemFeature(PackageManager.FEATURE_CAMERA_FRONT)

    override fun supportsWindowBlur(): Boolean = blurSupport.isAvailable()

    override fun hasScreenLock(): Boolean = OwnerAuthenticator.isAvailable(context)

    override fun maxOverlayOpacity(): Float = maxOverlayOpacity(context)

    private fun granted(permission: String) =
        ContextCompat.checkSelfPermission(context, permission) == PackageManager.PERMISSION_GRANTED

    companion object {
        fun maxOverlayOpacity(context: Context): Float =
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                context.getSystemService(InputManager::class.java)
                    ?.maximumObscuringOpacityForTouch ?: DEFAULT_MAX_OPACITY
            } else {
                1f
            }

        private const val DEFAULT_MAX_OPACITY = 0.8f
    }
}

/** Intents that open the system screens where the user grants special permissions. */
object PermissionIntents {
    fun overlaySettings(context: Context) = Intent(
        Settings.ACTION_MANAGE_OVERLAY_PERMISSION,
        Uri.parse("package:${context.packageName}"),
    ).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)

    fun usageAccessSettings() =
        Intent(Settings.ACTION_USAGE_ACCESS_SETTINGS).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)

    fun appDetails(context: Context) = Intent(
        Settings.ACTION_APPLICATION_DETAILS_SETTINGS,
        Uri.parse("package:${context.packageName}"),
    ).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
}
