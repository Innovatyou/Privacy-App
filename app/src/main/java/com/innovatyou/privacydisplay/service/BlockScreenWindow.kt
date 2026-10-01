package com.innovatyou.privacydisplay.service

import android.content.Context
import android.graphics.Color
import android.graphics.PixelFormat
import android.graphics.drawable.GradientDrawable
import android.os.Build
import android.util.TypedValue
import android.view.Gravity
import android.view.View
import android.view.WindowManager
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.TextView
import com.innovatyou.privacydisplay.R
import kotlin.math.roundToInt

/**
 * Full-screen, opaque, touchable window shown when someone other than the owner is using the phone.
 * It deliberately takes all touches so the apps underneath cannot be used. The owner can tap
 * "Unlock" to confirm with a fingerprint or PIN.
 *
 * Android still lets anyone open the notification shade, Quick Settings and system Settings, which
 * this window cannot cover, so it is a deterrent and not a replacement for the lock screen.
 */
class BlockScreenWindow(serviceContext: Context, private val onUnlock: () -> Unit) {

    private val windowContext = OverlayWindows.windowContext(serviceContext)
    private val windowManager = windowContext.getSystemService(WindowManager::class.java)
    private val density = serviceContext.resources.displayMetrics.density
    private var view: View? = null

    private val layoutParams = WindowManager.LayoutParams(
        WindowManager.LayoutParams.MATCH_PARENT,
        WindowManager.LayoutParams.MATCH_PARENT,
        WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY,
        WindowManager.LayoutParams.FLAG_LAYOUT_IN_SCREEN or
            WindowManager.LayoutParams.FLAG_LAYOUT_NO_LIMITS or
            WindowManager.LayoutParams.FLAG_HARDWARE_ACCELERATED,
        PixelFormat.OPAQUE,
    ).apply {
        title = "PrivacyDisplayBlock"
        gravity = Gravity.TOP or Gravity.START
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            layoutInDisplayCutoutMode = WindowManager.LayoutParams.LAYOUT_IN_DISPLAY_CUTOUT_MODE_ALWAYS
            fitInsetsTypes = 0
        } else if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            layoutInDisplayCutoutMode = WindowManager.LayoutParams.LAYOUT_IN_DISPLAY_CUTOUT_MODE_SHORT_EDGES
        }
    }

    val isShowing: Boolean get() = view != null

    fun show() {
        if (view != null) return
        OverlayWindows.applyScreenSize(windowManager, layoutParams)
        val root = LinearLayout(windowContext).apply {
            orientation = LinearLayout.VERTICAL
            gravity = Gravity.CENTER
            setBackgroundColor(Color.BLACK)
            setPadding(dp(32), dp(32), dp(32), dp(32))
            // Swallow every touch that does not hit the button.
            isClickable = true
            setOnTouchListener { _, _ -> true }
        }
        root.addView(ImageView(windowContext).apply {
            setImageResource(R.drawable.ic_shield)
            setColorFilter(Color.WHITE)
            importantForAccessibility = View.IMPORTANT_FOR_ACCESSIBILITY_NO
        }, LinearLayout.LayoutParams(dp(72), dp(72)))
        root.addView(text(R.string.block_title, 24f, Color.WHITE).apply { setPadding(0, dp(24), 0, dp(8)) })
        root.addView(text(R.string.block_text, 16f, Color.argb(220, 255, 255, 255)))
        root.addView(TextView(windowContext).apply {
            text = context.getString(R.string.block_unlock)
            contentDescription = context.getString(R.string.block_unlock_description)
            setTextColor(Color.WHITE)
            setTextSize(TypedValue.COMPLEX_UNIT_SP, 16f)
            gravity = Gravity.CENTER
            minHeight = dp(52)
            minWidth = dp(180)
            setPadding(dp(24), dp(12), dp(24), dp(12))
            background = GradientDrawable().apply {
                cornerRadius = dp(26).toFloat()
                setColor(0xFF2456D1.toInt())
            }
            isClickable = true
            isFocusable = true
            setOnClickListener { onUnlock() }
        }, LinearLayout.LayoutParams(
            LinearLayout.LayoutParams.WRAP_CONTENT, LinearLayout.LayoutParams.WRAP_CONTENT,
        ).apply { topMargin = dp(32) })

        windowManager.addView(root, layoutParams)
        view = root
    }

    fun hide() {
        view?.let { windowManager.removeView(it) }
        view = null
    }

    fun onDisplayChanged() {
        val current = view ?: return
        if (OverlayWindows.applyScreenSize(windowManager, layoutParams)) {
            windowManager.updateViewLayout(current, layoutParams)
        }
    }

    private fun text(res: Int, sizeSp: Float, color: Int) = TextView(windowContext).apply {
        setText(res)
        setTextColor(color)
        setTextSize(TypedValue.COMPLEX_UNIT_SP, sizeSp)
        gravity = Gravity.CENTER
    }

    private fun dp(value: Int) = (value * density).roundToInt()
}
