package com.innovatyou.privacyscreen

import android.annotation.SuppressLint
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.content.SharedPreferences
import android.content.pm.ServiceInfo
import android.content.res.Configuration
import android.graphics.PixelFormat
import android.graphics.Rect
import android.hardware.input.InputManager
import android.os.Build
import android.os.IBinder
import android.provider.Settings
import android.service.quicksettings.TileService
import android.util.DisplayMetrics
import android.view.Gravity
import android.view.MotionEvent
import android.view.View
import android.view.WindowManager
import android.widget.ImageView
import androidx.core.app.NotificationCompat
import androidx.core.content.ContextCompat

/**
 * Foreground service that keeps the privacy filter overlay on top of every app.
 */
class PrivacyOverlayService : Service(), SharedPreferences.OnSharedPreferenceChangeListener {

    private lateinit var windowManager: WindowManager
    private var overlay: PrivacyOverlayView? = null
    private var handle: View? = null
    private val overlayParams by lazy { buildOverlayParams() }
    private val handleParams by lazy { buildHandleParams() }

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onCreate() {
        super.onCreate()
        windowManager = getSystemService(WINDOW_SERVICE) as WindowManager
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (intent?.action == ACTION_STOP) {
            stopSelf()
            return START_NOT_STICKY
        }
        startInForeground()
        if (!Settings.canDrawOverlays(this)) {
            stopSelf()
            return START_NOT_STICKY
        }
        if (overlay == null) showOverlay()
        return START_STICKY
    }

    override fun onDestroy() {
        PrivacySettings.prefs(this).unregisterOnSharedPreferenceChangeListener(this)
        overlay?.let { windowManager.removeView(it) }
        handle?.let { windowManager.removeView(it) }
        overlay = null
        handle = null
        setRunning(this, false)
        super.onDestroy()
    }

    override fun onConfigurationChanged(newConfig: Configuration) {
        super.onConfigurationChanged(newConfig)
        // Screen rotated or resized: stretch the overlay to the new bounds.
        overlay?.let {
            applyScreenSize(overlayParams)
            windowManager.updateViewLayout(it, overlayParams)
        }
        handle?.let { positionHandle() }
    }

    override fun onSharedPreferenceChanged(prefs: SharedPreferences?, key: String?) {
        applySettings(PrivacySettings.load(this))
    }

    private fun showOverlay() {
        val view = PrivacyOverlayView(this, overlayParams.alpha)
        windowManager.addView(view, overlayParams)
        overlay = view
        PrivacySettings.prefs(this).registerOnSharedPreferenceChangeListener(this)
        applySettings(PrivacySettings.load(this))
        setRunning(this, true)
    }

    private fun applySettings(settings: PrivacySettings) {
        overlay?.settings = settings
        if (settings.spotlight && handle == null) {
            val view = createHandle()
            windowManager.addView(view, handleParams)
            handle = view
            positionHandle()
        } else if (!settings.spotlight && handle != null) {
            windowManager.removeView(handle)
            handle = null
        } else if (handle != null) {
            positionHandle()
        }
    }

    // ---- Spotlight drag handle ----------------------------------------------------------------

    @SuppressLint("ClickableViewAccessibility")
    private fun createHandle(): View = ImageView(this).apply {
        setImageResource(R.drawable.ic_drag_handle)
        setBackgroundResource(R.drawable.bg_handle)
        contentDescription = getString(R.string.handle_description)
        var downRawY = 0f
        var downCenter = 0f
        setOnTouchListener { _, event ->
            val screenHeight = screenBounds().height().toFloat()
            when (event.actionMasked) {
                MotionEvent.ACTION_DOWN -> {
                    downRawY = event.rawY
                    downCenter = overlay?.settings?.spotlightCenter ?: 0.5f
                }
                MotionEvent.ACTION_MOVE, MotionEvent.ACTION_UP -> {
                    val center = (downCenter + (event.rawY - downRawY) / screenHeight).coerceIn(0f, 1f)
                    val updated = (overlay?.settings ?: PrivacySettings()).copy(spotlightCenter = center)
                    overlay?.settings = updated
                    positionHandle()
                    // Persist only when the finger lifts to avoid a flood of preference writes.
                    if (event.actionMasked == MotionEvent.ACTION_UP) updated.save(this@PrivacyOverlayService)
                }
            }
            true
        }
    }

    private fun positionHandle() {
        val view = handle ?: return
        val settings = overlay?.settings ?: return
        val screenHeight = screenBounds().height()
        val bandHeight = screenHeight * settings.spotlightHeightPercent / 100f
        val top = (screenHeight * settings.spotlightCenter - bandHeight / 2)
            .coerceIn(0f, screenHeight - bandHeight)
        handleParams.y = (top + bandHeight / 2 - handleParams.height / 2).toInt()
        windowManager.updateViewLayout(view, handleParams)
    }

    // ---- Window parameters --------------------------------------------------------------------

    private fun buildOverlayParams() = WindowManager.LayoutParams(
        0, 0,
        WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY,
        WindowManager.LayoutParams.FLAG_NOT_TOUCHABLE or
            WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE or
            WindowManager.LayoutParams.FLAG_LAYOUT_IN_SCREEN or
            WindowManager.LayoutParams.FLAG_LAYOUT_NO_LIMITS,
        PixelFormat.TRANSLUCENT,
    ).apply {
        gravity = Gravity.TOP or Gravity.START
        // Android 12+ ignores touches through overlays that are too opaque; stay at the limit.
        alpha = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            (getSystemService(INPUT_SERVICE) as InputManager).maximumObscuringOpacityForTouch
        } else {
            1f
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            layoutInDisplayCutoutMode = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                WindowManager.LayoutParams.LAYOUT_IN_DISPLAY_CUTOUT_MODE_ALWAYS
            } else {
                WindowManager.LayoutParams.LAYOUT_IN_DISPLAY_CUTOUT_MODE_SHORT_EDGES
            }
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) fitInsetsTypes = 0
        applyScreenSize(this)
    }

    private fun buildHandleParams(): WindowManager.LayoutParams {
        val density = resources.displayMetrics.density
        return WindowManager.LayoutParams(
            (36 * density).toInt(), (72 * density).toInt(),
            WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY,
            WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE or
                WindowManager.LayoutParams.FLAG_LAYOUT_IN_SCREEN,
            PixelFormat.TRANSLUCENT,
        ).apply {
            gravity = Gravity.TOP or Gravity.END
        }
    }

    private fun applyScreenSize(params: WindowManager.LayoutParams) {
        val bounds = screenBounds()
        params.width = bounds.width()
        params.height = bounds.height()
        params.x = 0
        params.y = 0
    }

    /** Full physical screen size, including status and navigation bars. */
    private fun screenBounds(): Rect =
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            windowManager.maximumWindowMetrics.bounds
        } else {
            val metrics = DisplayMetrics()
            @Suppress("DEPRECATION")
            windowManager.defaultDisplay.getRealMetrics(metrics)
            Rect(0, 0, metrics.widthPixels, metrics.heightPixels)
        }

    // ---- Foreground notification --------------------------------------------------------------

    private fun startInForeground() {
        val manager = getSystemService(NOTIFICATION_SERVICE) as NotificationManager
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            manager.createNotificationChannel(
                NotificationChannel(
                    CHANNEL_ID,
                    getString(R.string.notification_channel),
                    NotificationManager.IMPORTANCE_LOW,
                )
            )
        }
        val openApp = PendingIntent.getActivity(
            this, 0, Intent(this, MainActivity::class.java),
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT,
        )
        val stop = PendingIntent.getService(
            this, 1, Intent(this, PrivacyOverlayService::class.java).setAction(ACTION_STOP),
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT,
        )
        val notification: Notification = NotificationCompat.Builder(this, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_shield)
            .setContentTitle(getString(R.string.notification_title))
            .setContentText(getString(R.string.notification_text))
            .setContentIntent(openApp)
            .addAction(0, getString(R.string.action_stop), stop)
            .setOngoing(true)
            .setSilent(true)
            .build()

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
            startForeground(NOTIFICATION_ID, notification, ServiceInfo.FOREGROUND_SERVICE_TYPE_SPECIAL_USE)
        } else {
            startForeground(NOTIFICATION_ID, notification)
        }
    }

    companion object {
        private const val CHANNEL_ID = "privacy_filter"
        private const val NOTIFICATION_ID = 1
        private const val ACTION_STOP = "com.innovatyou.privacyscreen.STOP"

        /** Whether the overlay is currently showing. Observed by the UI and the Quick Settings tile. */
        @Volatile
        var isRunning: Boolean = false
            private set

        private val listeners = mutableSetOf<(Boolean) -> Unit>()

        fun addRunningListener(listener: (Boolean) -> Unit) {
            synchronized(listeners) { listeners += listener }
        }

        fun removeRunningListener(listener: (Boolean) -> Unit) {
            synchronized(listeners) { listeners -= listener }
        }

        private fun setRunning(context: Context, running: Boolean) {
            isRunning = running
            synchronized(listeners) { listeners.toList() }.forEach { it(running) }
            TileService.requestListeningState(
                context, ComponentName(context, PrivacyTileService::class.java)
            )
        }

        fun start(context: Context) {
            ContextCompat.startForegroundService(context, Intent(context, PrivacyOverlayService::class.java))
        }

        fun stop(context: Context) {
            context.stopService(Intent(context, PrivacyOverlayService::class.java))
        }
    }
}
