package com.innovatyou.privacyscreen

import android.annotation.SuppressLint
import android.app.PendingIntent
import android.content.Intent
import android.os.Build
import android.provider.Settings
import android.service.quicksettings.Tile
import android.service.quicksettings.TileService

/** Quick Settings tile that toggles the privacy filter from the notification shade. */
class PrivacyTileService : TileService() {

    override fun onStartListening() {
        super.onStartListening()
        updateTile()
    }

    override fun onClick() {
        super.onClick()
        when {
            PrivacyOverlayService.isRunning -> PrivacyOverlayService.stop(this)
            Settings.canDrawOverlays(this) -> {
                try {
                    PrivacyOverlayService.start(this)
                } catch (e: IllegalStateException) {
                    // Some Android versions refuse to start a foreground service from a tile;
                    // fall back to the app, which starts the filter itself.
                    openApp(startFilter = true)
                }
            }
            else -> openApp(startFilter = false)
        }
        updateTile()
    }

    @SuppressLint("StartActivityAndCollapseDeprecated")
    private fun openApp(startFilter: Boolean) {
        val intent = Intent(this, MainActivity::class.java)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            .putExtra(MainActivity.EXTRA_START_FILTER, startFilter)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
            startActivityAndCollapse(
                PendingIntent.getActivity(this, 0, intent, PendingIntent.FLAG_IMMUTABLE)
            )
        } else {
            @Suppress("DEPRECATION")
            startActivityAndCollapse(intent)
        }
    }

    private fun updateTile() {
        val tile = qsTile ?: return
        tile.state = if (PrivacyOverlayService.isRunning) Tile.STATE_ACTIVE else Tile.STATE_INACTIVE
        tile.updateTile()
    }
}
