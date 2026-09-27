package com.innovatyou.privacyscreen

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.provider.Settings
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.core.view.isVisible
import com.innovatyou.privacyscreen.databinding.ActivityMainBinding

class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding
    private var settings = PrivacySettings()
    private var startAfterPermission = false

    private val runningListener: (Boolean) -> Unit = { running ->
        runOnUiThread { renderRunning(running) }
    }

    private val notificationPermission =
        registerForActivityResult(ActivityResultContracts.RequestPermission()) { /* optional */ }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        settings = PrivacySettings.load(this)
        bindSettingsToViews()
        setUpListeners()

        startAfterPermission = intent.getBooleanExtra(EXTRA_START_FILTER, false)
        maybeRequestNotificationPermission()
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        if (intent.getBooleanExtra(EXTRA_START_FILTER, false)) startAfterPermission = true
    }

    override fun onResume() {
        super.onResume()
        PrivacyOverlayService.addRunningListener(runningListener)
        val canDraw = Settings.canDrawOverlays(this)
        binding.permissionCard.isVisible = !canDraw
        binding.toggleButton.isEnabled = canDraw
        if (canDraw && startAfterPermission) {
            startAfterPermission = false
            PrivacyOverlayService.start(this)
        }
        renderRunning(PrivacyOverlayService.isRunning)
    }

    override fun onPause() {
        PrivacyOverlayService.removeRunningListener(runningListener)
        super.onPause()
    }

    private fun bindSettingsToViews() = with(binding) {
        dimSlider.value = settings.dimPercent.toFloat()
        patternStrengthSlider.value = settings.patternPercent.toFloat()
        patternGroup.check(
            when (settings.pattern) {
                FilterPattern.NONE -> R.id.patternNone
                FilterPattern.VERTICAL_LINES -> R.id.patternLines
                FilterPattern.GRID -> R.id.patternGrid
                FilterPattern.DOTS -> R.id.patternDots
            }
        )
        patternStrengthSlider.isEnabled = settings.pattern != FilterPattern.NONE
        spotlightSwitch.isChecked = settings.spotlight
        spotlightHeightSlider.value = settings.spotlightHeightPercent.toFloat()
        spotlightHeightSlider.isEnabled = settings.spotlight
    }

    private fun setUpListeners() = with(binding) {
        grantPermissionButton.setOnClickListener {
            startAfterPermission = true
            startActivity(
                Intent(Settings.ACTION_MANAGE_OVERLAY_PERMISSION, Uri.parse("package:$packageName"))
            )
        }
        toggleButton.setOnClickListener {
            if (PrivacyOverlayService.isRunning) {
                PrivacyOverlayService.stop(this@MainActivity)
            } else {
                PrivacyOverlayService.start(this@MainActivity)
            }
        }
        dimSlider.addOnChangeListener { _, value, fromUser ->
            if (fromUser) update(settings.copy(dimPercent = value.toInt()))
        }
        patternStrengthSlider.addOnChangeListener { _, value, fromUser ->
            if (fromUser) update(settings.copy(patternPercent = value.toInt()))
        }
        patternGroup.setOnCheckedChangeListener { _, checkedId ->
            val pattern = when (checkedId) {
                R.id.patternLines -> FilterPattern.VERTICAL_LINES
                R.id.patternGrid -> FilterPattern.GRID
                R.id.patternDots -> FilterPattern.DOTS
                else -> FilterPattern.NONE
            }
            patternStrengthSlider.isEnabled = pattern != FilterPattern.NONE
            update(settings.copy(pattern = pattern))
        }
        spotlightSwitch.setOnCheckedChangeListener { _, checked ->
            spotlightHeightSlider.isEnabled = checked
            update(settings.copy(spotlight = checked))
        }
        spotlightHeightSlider.addOnChangeListener { _, value, fromUser ->
            if (fromUser) update(settings.copy(spotlightHeightPercent = value.toInt()))
        }
    }

    private fun update(newSettings: PrivacySettings) {
        // Keep the band position the drag handle may have changed while we were in the background.
        settings = newSettings.copy(spotlightCenter = PrivacySettings.load(this).spotlightCenter)
        settings.save(this) // The running service observes the preferences and redraws live.
    }

    private fun renderRunning(running: Boolean) = with(binding) {
        statusText.setText(if (running) R.string.status_on else R.string.status_off)
        toggleButton.setText(if (running) R.string.turn_off else R.string.turn_on)
        statusIcon.alpha = if (running) 1f else 0.4f
    }

    private fun maybeRequestNotificationPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) !=
            PackageManager.PERMISSION_GRANTED
        ) {
            notificationPermission.launch(Manifest.permission.POST_NOTIFICATIONS)
        }
    }

    companion object {
        const val EXTRA_START_FILTER = "start_filter"
    }
}
