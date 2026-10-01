package com.innovatyou.privacydisplay.service

import android.Manifest
import android.annotation.SuppressLint
import android.app.Notification
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.app.NotificationChannelCompat
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import com.innovatyou.privacydisplay.R
import com.innovatyou.privacydisplay.camera.ViewerSide
import com.innovatyou.privacydisplay.ui.MainActivity
import dagger.hilt.android.qualifiers.ApplicationContext
import javax.inject.Inject
import javax.inject.Singleton

/** Content of the persistent service notification. */
data class StatusContent(
    val title: String,
    val text: String,
    val privacyEnabled: Boolean,
    val sharing: Boolean = false,
)

@Singleton
class PrivacyNotification @Inject constructor(
    @ApplicationContext private val context: Context,
) {
    private val manager = NotificationManagerCompat.from(context)

    fun createChannels() {
        manager.createNotificationChannelsCompat(
            listOf(
                NotificationChannelCompat.Builder(STATUS_CHANNEL, NotificationManagerCompat.IMPORTANCE_LOW)
                    .setName(context.getString(R.string.notification_channel_status))
                    .setDescription(context.getString(R.string.notification_channel_status_desc))
                    .setShowBadge(false)
                    .build(),
                NotificationChannelCompat.Builder(ALERT_CHANNEL, NotificationManagerCompat.IMPORTANCE_DEFAULT)
                    .setName(context.getString(R.string.notification_channel_alerts))
                    .setDescription(context.getString(R.string.notification_channel_alerts_desc))
                    .setSound(null, null)
                    .setVibrationEnabled(false)
                    .build(),
            )
        )
    }

    fun buildStatus(content: StatusContent): Notification {
        val action = if (content.sharing) {
            NotificationCompat.Action(
                0, context.getString(R.string.resume_privacy),
                servicePendingIntent(PrivacyOverlayService.ACTION_STOP_SHARING, REQUEST_STOP_SHARING),
            )
        } else if (content.privacyEnabled) {
            NotificationCompat.Action(
                0, context.getString(R.string.notif_action_turn_off),
                servicePendingIntent(PrivacyOverlayService.ACTION_DISABLE, REQUEST_DISABLE),
            )
        } else {
            NotificationCompat.Action(
                0, context.getString(R.string.notif_action_turn_on),
                servicePendingIntent(PrivacyOverlayService.ACTION_ENABLE, REQUEST_ENABLE),
            )
        }
        return NotificationCompat.Builder(context, STATUS_CHANNEL)
            .setSmallIcon(R.drawable.ic_shield)
            .setContentTitle(content.title)
            .setContentText(content.text)
            .setStyle(NotificationCompat.BigTextStyle().bigText(content.text))
            .setContentIntent(openAppPendingIntent())
            .addAction(action)
            .setOngoing(true)
            .setSilent(true)
            .setShowWhen(false)
            .setCategory(NotificationCompat.CATEGORY_SERVICE)
            .setForegroundServiceBehavior(NotificationCompat.FOREGROUND_SERVICE_IMMEDIATE)
            .build()
    }

    @SuppressLint("MissingPermission") // Checked by canPost().
    fun updateStatus(content: StatusContent) {
        if (canPost()) manager.notify(STATUS_ID, buildStatus(content))
    }

    @SuppressLint("MissingPermission") // Checked by canPost().
    fun showViewerAlert(side: ViewerSide?) {
        if (!canPost()) return
        val notification = NotificationCompat.Builder(context, ALERT_CHANNEL)
            .setSmallIcon(R.drawable.ic_shield)
            .setContentTitle(context.getString(R.string.alert_title))
            .setContentText(context.getString(alertText(side)))
            .setContentIntent(openAppPendingIntent())
            .addAction(
                0, context.getString(R.string.share_screen),
                servicePendingIntent(PrivacyOverlayService.ACTION_SHARE, REQUEST_SHARE),
            )
            .setAutoCancel(true)
            .setTimeoutAfter(ALERT_TIMEOUT_MS)
            .setCategory(NotificationCompat.CATEGORY_STATUS)
            .build()
        manager.notify(ALERT_ID, notification)
    }

    fun cancelViewerAlert() = manager.cancel(ALERT_ID)

    private fun alertText(side: ViewerSide?): Int = when (side) {
        ViewerSide.LEFT -> R.string.alert_text_left
        ViewerSide.RIGHT -> R.string.alert_text_right
        ViewerSide.ABOVE -> R.string.alert_text_above
        ViewerSide.BELOW -> R.string.alert_text_below
        null -> R.string.alert_text
    }

    private fun canPost(): Boolean =
        Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU ||
            ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) ==
            PackageManager.PERMISSION_GRANTED

    private fun openAppPendingIntent(): PendingIntent = PendingIntent.getActivity(
        context, REQUEST_OPEN,
        Intent(context, MainActivity::class.java).addFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP),
        PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT,
    )

    private fun servicePendingIntent(action: String, requestCode: Int): PendingIntent =
        PendingIntent.getService(
            context, requestCode,
            Intent(context, PrivacyOverlayService::class.java).setAction(action),
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT,
        )

    companion object {
        const val STATUS_ID = 1
        private const val ALERT_ID = 2
        private const val STATUS_CHANNEL = "privacy_status"
        private const val ALERT_CHANNEL = "viewer_alerts"
        private const val REQUEST_OPEN = 0
        private const val REQUEST_DISABLE = 1
        private const val REQUEST_ENABLE = 2
        private const val REQUEST_SHARE = 3
        private const val REQUEST_STOP_SHARING = 4
        private const val ALERT_TIMEOUT_MS = 15_000L
    }
}
