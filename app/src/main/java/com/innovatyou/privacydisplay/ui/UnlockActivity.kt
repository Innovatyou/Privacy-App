package com.innovatyou.privacydisplay.ui

import android.content.Context
import android.content.Intent
import android.os.Bundle
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.fragment.app.FragmentActivity
import androidx.lifecycle.lifecycleScope
import com.innovatyou.privacydisplay.R
import com.innovatyou.privacydisplay.data.PrivacySettings
import com.innovatyou.privacydisplay.owner.OwnerAuthenticator
import com.innovatyou.privacydisplay.service.PrivacyController
import com.innovatyou.privacydisplay.service.PrivacyRuntime
import com.innovatyou.privacydisplay.ui.theme.PrivacyDisplayTheme
import dagger.hilt.android.AndroidEntryPoint
import javax.inject.Inject
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

/**
 * Asks the owner for a fingerprint or PIN before something that weakens owner protection:
 * unblocking the phone, lending it for a while, or turning Privacy Mode off from the tile or
 * notification.
 */
@AndroidEntryPoint
class UnlockActivity : FragmentActivity() {

    @Inject lateinit var controller: PrivacyController
    @Inject lateinit var runtime: PrivacyRuntime

    private var authenticating = false
    private var timeout: Job? = null
    private var showLendChoices by mutableStateOf(false)

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val mode = intent.getStringExtra(EXTRA_MODE)?.let { runCatching { Mode.valueOf(it) }.getOrNull() } ?: Mode.UNLOCK
        setContent {
            PrivacyDisplayTheme {
                Surface(modifier = Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
                    if (showLendChoices) {
                        LendChoices(
                            showItsMe = mode == Mode.UNLOCK,
                            onItsMe = { finish() },
                            onLend = { minutes ->
                                controller.startSharing(minutes)
                                finish()
                            },
                        )
                    }
                }
            }
        }
        if (savedInstanceState == null) authenticate(mode)
    }

    private fun authenticate(mode: Mode) {
        if (!OwnerAuthenticator.isAvailable(this)) {
            finish()
            return
        }
        authenticating = true
        runtime.setUnlockScreenOpen(true)
        // Never keep the block screen away for long, even if the prompt is left open.
        timeout = lifecycleScope.launch {
            delay(AUTH_TIMEOUT_MS)
            finish()
        }
        OwnerAuthenticator.authenticate(
            activity = this,
            title = getString(R.string.auth_title),
            subtitle = getString(
                when (mode) {
                    Mode.UNLOCK -> R.string.auth_subtitle_unlock
                    Mode.DISABLE -> R.string.auth_subtitle_disable
                    Mode.SHARE -> R.string.auth_subtitle_share
                }
            ),
            onSuccess = {
                authenticating = false
                timeout?.cancel()
                controller.ownerAuthenticated()
                when (mode) {
                    Mode.DISABLE -> lifecycleScope.launch {
                        controller.setPrivacyEnabled(false)
                        finish()
                    }
                    Mode.UNLOCK, Mode.SHARE -> showLendChoices = true
                }
            },
            onFailure = {
                authenticating = false
                finish()
            },
        )
    }

    override fun onStop() {
        super.onStop()
        // Leaving this screen without confirming brings the block screen back.
        if (!authenticating && !isChangingConfigurations) finish()
    }

    override fun onDestroy() {
        if (!isChangingConfigurations) runtime.setUnlockScreenOpen(false)
        super.onDestroy()
    }

    enum class Mode { UNLOCK, DISABLE, SHARE }

    companion object {
        private const val EXTRA_MODE = "mode"
        private const val AUTH_TIMEOUT_MS = 60_000L

        fun intent(context: Context, mode: Mode): Intent =
            Intent(context, UnlockActivity::class.java)
                .putExtra(EXTRA_MODE, mode.name)
                .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP)
    }
}

@androidx.compose.runtime.Composable
private fun LendChoices(showItsMe: Boolean, onItsMe: () -> Unit, onLend: (Int) -> Unit) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(24.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp, Alignment.CenterVertically),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(
            stringResource(R.string.lend_title),
            style = MaterialTheme.typography.headlineSmall,
            textAlign = TextAlign.Center,
            modifier = Modifier.semantics { heading() },
        )
        Text(
            stringResource(R.string.lend_text),
            style = MaterialTheme.typography.bodyLarge,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center,
        )
        PrivacySettings.SHARE_MINUTE_OPTIONS.forEach { minutes ->
            OutlinedButton(
                onClick = { onLend(minutes) },
                modifier = Modifier
                    .fillMaxWidth()
                    .heightIn(min = 52.dp),
            ) { Text(stringResource(R.string.lend_for_minutes, minutes)) }
        }
        if (showItsMe) {
            Button(
                onClick = onItsMe,
                modifier = Modifier
                    .fillMaxWidth()
                    .heightIn(min = 52.dp),
            ) { Text(stringResource(R.string.its_me)) }
        }
    }
}
