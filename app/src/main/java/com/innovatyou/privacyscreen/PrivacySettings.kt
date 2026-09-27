package com.innovatyou.privacyscreen

import android.content.Context
import android.content.SharedPreferences

/** Pattern drawn over the screen to break up the image for people viewing at an angle. */
enum class FilterPattern { NONE, VERTICAL_LINES, GRID, DOTS }

/** User-tunable filter settings, persisted in [SharedPreferences]. */
data class PrivacySettings(
    /** Darkness of the tint over the whole screen, 0–100. */
    val dimPercent: Int = 45,
    val pattern: FilterPattern = FilterPattern.VERTICAL_LINES,
    /** Opacity of the pattern, 0–100. */
    val patternPercent: Int = 35,
    /** When on, only a horizontal band is readable and the rest of the screen is blacked out. */
    val spotlight: Boolean = false,
    /** Height of the readable band as a percentage of screen height, 10–70. */
    val spotlightHeightPercent: Int = 30,
    /** Vertical centre of the readable band, 0.0 (top) – 1.0 (bottom). */
    val spotlightCenter: Float = 0.5f,
) {
    fun save(context: Context) {
        prefs(context).edit()
            .putInt(KEY_DIM, dimPercent)
            .putString(KEY_PATTERN, pattern.name)
            .putInt(KEY_PATTERN_STRENGTH, patternPercent)
            .putBoolean(KEY_SPOTLIGHT, spotlight)
            .putInt(KEY_SPOTLIGHT_HEIGHT, spotlightHeightPercent)
            .putFloat(KEY_SPOTLIGHT_CENTER, spotlightCenter)
            .apply()
    }

    companion object {
        private const val PREFS_NAME = "privacy_settings"
        private const val KEY_DIM = "dim"
        private const val KEY_PATTERN = "pattern"
        private const val KEY_PATTERN_STRENGTH = "pattern_strength"
        private const val KEY_SPOTLIGHT = "spotlight"
        private const val KEY_SPOTLIGHT_HEIGHT = "spotlight_height"
        private const val KEY_SPOTLIGHT_CENTER = "spotlight_center"

        fun prefs(context: Context): SharedPreferences =
            context.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

        fun load(context: Context): PrivacySettings {
            val p = prefs(context)
            val defaults = PrivacySettings()
            return PrivacySettings(
                dimPercent = p.getInt(KEY_DIM, defaults.dimPercent),
                pattern = p.getString(KEY_PATTERN, null)
                    ?.let { name -> FilterPattern.entries.firstOrNull { it.name == name } }
                    ?: defaults.pattern,
                patternPercent = p.getInt(KEY_PATTERN_STRENGTH, defaults.patternPercent),
                spotlight = p.getBoolean(KEY_SPOTLIGHT, defaults.spotlight),
                spotlightHeightPercent = p.getInt(KEY_SPOTLIGHT_HEIGHT, defaults.spotlightHeightPercent),
                spotlightCenter = p.getFloat(KEY_SPOTLIGHT_CENTER, defaults.spotlightCenter),
            )
        }
    }
}
