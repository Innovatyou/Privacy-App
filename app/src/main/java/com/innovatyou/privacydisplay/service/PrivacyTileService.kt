package com.innovatyou.privacydisplay.service

import android.annotation.SuppressLint
import android.app.PendingIntent
import android.content.Intent
import android.os.Build
import android.service.quicksettings.Tile
import android.service.quicksettings.TileService
import com.innovatyou.privacydisplay.R
import com.innovatyou.privacydisplay.data.PreferencesRepository
import com.innovatyou.privacydisplay.ui.MainActivity
import com.innovatyou.privacydisplay.owner.OwnerFaceStore
import com.innovatyou.privacydisplay.owner.OwnerPolicy
import com.innovatyou.privacydisplay.ui.ToggleActivity
import com.innovatyou.privacydisplay.ui.UnlockActivity
import com.innovatyou.privacydisplay.util.PermissionManager
import dagger.hilt.android.AndroidEntryPoint
import javax.inject.Inject
import kotlinx.coroutines.Job
import kotlinx.coroutines.MainScope
import kotlinx.coroutines.cancel
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch

/** Quick Settings tile: "Privacy Display — On / Off". */
@AndroidEntryPoint
class PrivacyTileService : TileService() {

    @Inject lateinit var repository: PreferencesRepository
    @Inject lateinit var controller: PrivacyController
    @Inject lateinit var permissions: PermissionManager
    @Inject lateinit var ownerFaces: OwnerFaceStore

    private val scope = MainScope()
    private var listening: Job? = null

    override fun onStartListening() {
        super.onStartListening()
        listening?.cancel()
        listening = scope.launch { repository.settings.collect { updateTile(it.enabled) } }
    }

    override fun onStopListening() {
        listening?.cancel()
        listening = null
        super.onStopListening()
    }

    override fun onDestroy() {
        scope.cancel()
        super.onDestroy()
    }

    override fun onClick() {
        super.onClick()
        if (!permissions.canDrawOverlays()) {
            // The overlay permission can only be granted from the app.
            openActivity(Intent(this, MainActivity::class.java), REQUEST_MAIN)
            return
        }
        scope.launch {
            val settings = repository.settings.first()
            val enable = !settings.enabled
            if (!enable && OwnerPolicy.isActive(settings, ownerFaces.enrolled.value)) {
                // Owner protection: only the owner may turn privacy off.
                openActivity(UnlockActivity.intent(this@PrivacyTileService, UnlockActivity.Mode.DISABLE), REQUEST_UNLOCK)
                return@launch
            }
            updateTile(enable)
            if (!controller.setPrivacyEnabled(enable)) {
                // Android refused a background service start: hand over to an invisible activity.
                openActivity(ToggleActivity.intent(this@PrivacyTileService, enable), REQUEST_TOGGLE)
            }
        }
    }

    private fun updateTile(enabled: Boolean) {
        val tile = qsTile ?: return
        tile.state = if (enabled) Tile.STATE_ACTIVE else Tile.STATE_INACTIVE
        tile.label = getString(R.string.tile_label)
        val stateText = getString(if (enabled) R.string.state_on else R.string.state_off)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) tile.subtitle = stateText
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) tile.stateDescription = stateText
        tile.updateTile()
    }

    @SuppressLint("StartActivityAndCollapseDeprecated")
    private fun openActivity(intent: Intent, requestCode: Int) {
        intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
            startActivityAndCollapse(
                PendingIntent.getActivity(
                    this, requestCode, intent,
                    PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT,
                )
            )
        } else {
            @Suppress("DEPRECATION")
            startActivityAndCollapse(intent)
        }
    }

    private companion object {
        const val REQUEST_MAIN = 10
        const val REQUEST_TOGGLE = 11
        const val REQUEST_UNLOCK = 12
    }
}
