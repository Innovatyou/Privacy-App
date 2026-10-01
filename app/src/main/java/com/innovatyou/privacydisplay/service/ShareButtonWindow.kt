package com.innovatyou.privacydisplay.service

import android.content.Context
import android.graphics.PixelFormat
import android.graphics.drawable.GradientDrawable
import android.util.TypedValue
import android.view.Gravity
import android.view.WindowManager
import android.widget.TextView
import com.innovatyou.privacydisplay.R
import kotlin.math.roundToInt

/**
 * A small floating "Share screen" button shown above the privacy mask and blur while a second
 * person is detected. Tapping it turns privacy off for a while so both people can see the screen.
 * Unlike the mask, this window is touchable, but only over its own small area.
 */
class ShareButtonWindow(serviceContext: Context, private val onShare: () -> Unit) {

    private val windowContext = OverlayWindows.windowContext(serviceContext)
    private val windowManager = windowContext.getSystemService(WindowManager::class.java)
    private val density = serviceContext.resources.displayMetrics.density
    private var view: TextView? = null

    private val layoutParams = WindowManager.LayoutParams(
        WindowManager.LayoutParams.WRAP_CONTENT,
        WindowManager.LayoutParams.WRAP_CONTENT,
        WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY,
        WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE or
            WindowManager.LayoutParams.FLAG_LAYOUT_IN_SCREEN,
        PixelFormat.TRANSLUCENT,
    ).apply {
        title = "PrivacyDisplayShare"
        gravity = Gravity.BOTTOM or Gravity.CENTER_HORIZONTAL
        y = dp(96)
    }

    val isShowing: Boolean get() = view != null

    fun show() {
        if (view != null) return
        val button = TextView(windowContext).apply {
            text = context.getString(R.string.share_screen)
            contentDescription = context.getString(R.string.share_screen_description)
            setTextColor(BUTTON_TEXT)
            setTextSize(TypedValue.COMPLEX_UNIT_SP, 16f)
            minHeight = dp(52)
            minWidth = dp(160)
            gravity = Gravity.CENTER
            setPadding(dp(24), dp(12), dp(24), dp(12))
            elevation = dp(6).toFloat()
            background = GradientDrawable().apply {
                cornerRadius = dp(26).toFloat()
                setColor(BUTTON_COLOR)
            }
            isClickable = true
            isFocusable = true
            setOnClickListener { onShare() }
        }
        windowManager.addView(button, layoutParams)
        view = button
    }

    fun hide() {
        view?.let { windowManager.removeView(it) }
        view = null
    }

    private fun dp(value: Int) = (value * density).roundToInt()

    private companion object {
        const val BUTTON_COLOR = 0xFF2456D1.toInt()
        const val BUTTON_TEXT = 0xFFFFFFFF.toInt()
    }
}
