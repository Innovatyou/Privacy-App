package com.innovatyou.privacydisplay.ui

import android.content.Context
import android.content.Intent
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.lifecycle.lifecycleScope
import com.innovatyou.privacydisplay.service.PrivacyController
import dagger.hilt.android.AndroidEntryPoint
import javax.inject.Inject
import kotlinx.coroutines.launch

/**
 * Invisible activity used by the Quick Settings tile. Android 12+ may refuse to start a foreground
 * service from a tile tap; starting it while this activity is in front is always allowed.
 */
@AndroidEntryPoint
class ToggleActivity : ComponentActivity() {

    @Inject lateinit var controller: PrivacyController

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val enable = intent.getBooleanExtra(EXTRA_ENABLE, true)
        lifecycleScope.launch {
            controller.setPrivacyEnabled(enable)
            finish()
        }
    }

    companion object {
        private const val EXTRA_ENABLE = "enable"

        fun intent(context: Context, enable: Boolean): Intent =
            Intent(context, ToggleActivity::class.java).putExtra(EXTRA_ENABLE, enable)
    }
}
