package com.innovatyou.privacydisplay.data

import androidx.datastore.core.DataMigration
import androidx.datastore.preferences.core.MutablePreferences
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.booleanPreferencesKey

/**
 * Apps that should be ignored by default.
 *
 * Google Play Store and Android's package installer protect their Install buttons against
 * tap-jacking: they ignore taps while another app's overlay covers the screen
 * (`filterTouchesWhenObscured`). With the privacy mask on, "Install" and "Update" stop working, which
 * also blocks installing updates of this app. Ignoring these apps fixes that.
 */
object RecommendedExclusions {
    const val PLAY_STORE = "com.android.vending"

    val PACKAGES: Set<String> = linkedSetOf(
        PLAY_STORE,
        "com.google.android.packageinstaller", // Package installer on Google Android builds.
        "com.android.packageinstaller", // Package installer on AOSP-based builds.
    )

    private val APPLIED_KEY = booleanPreferencesKey("recommended_exclusions_v1")

    /** Adds the recommended apps once for people who installed an earlier version. */
    val migration: DataMigration<Preferences> = object : DataMigration<Preferences> {
        override suspend fun shouldMigrate(currentData: Preferences): Boolean = currentData[APPLIED_KEY] == null

        override suspend fun migrate(currentData: Preferences): Preferences =
            currentData.toMutablePreferences().also { applyTo(it) }

        override suspend fun cleanUp() = Unit
    }

    internal fun applyTo(prefs: MutablePreferences) {
        // When nothing was stored yet, the default (which already includes them) applies.
        prefs[PreferenceKeys.EXCLUDED_APPS]?.let { stored ->
            prefs[PreferenceKeys.EXCLUDED_APPS] = stored + PACKAGES
        }
        prefs[APPLIED_KEY] = true
    }
}
