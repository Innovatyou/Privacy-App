package com.innovatyou.privacydisplay.data

import androidx.datastore.preferences.core.emptyPreferences
import androidx.datastore.preferences.core.mutablePreferencesOf
import org.junit.Assert.assertEquals
import org.junit.Test

class PreferencesMappingTest {

    @Test
    fun `empty preferences give the defaults`() {
        assertEquals(PrivacySettings(), emptyPreferences().toPrivacySettings())
    }

    @Test
    fun `settings survive a round trip`() {
        val settings = PrivacySettings(
            enabled = true,
            strength = 0.3f,
            clearAreaWidth = 0.6f,
            clearAreaHeight = 0.7f,
            edgeOpacity = 0.4f,
            faceDetectionEnabled = true,
            multipleViewerProtection = true,
            maskMode = MaskMode.GRADIENT,
            gradientWidth = 0.2f,
            strongestMaskOnMultipleViewers = false,
            autoEnableOnUnlock = true,
            batterySaver = false,
            themeMode = ThemeMode.DARK,
            excludedApps = setOf("com.example.bank", "com.example.maps"),
            blurOnExtraViewer = false,
            blurWhenAway = true,
            blurStrength = 0.25f,
            shareMinutes = 30,
        )
        val prefs = mutablePreferencesOf()
        prefs.writePrivacySettings(settings)
        assertEquals(settings, prefs.toPrivacySettings())
    }

    @Test
    fun `unknown enum names fall back to defaults`() {
        val prefs = mutablePreferencesOf(
            PreferenceKeys.MASK_MODE to "HOLOGRAM",
            PreferenceKeys.THEME_MODE to "NEON",
        )
        val settings = prefs.toPrivacySettings()
        assertEquals(PrivacySettings().maskMode, settings.maskMode)
        assertEquals(PrivacySettings().themeMode, settings.themeMode)
    }

    @Test
    fun `stored values are clamped to valid ranges`() {
        val prefs = mutablePreferencesOf(
            PreferenceKeys.STRENGTH to 4f,
            PreferenceKeys.CLEAR_WIDTH to 0f,
            PreferenceKeys.GRADIENT_WIDTH to 2f,
        )
        val settings = prefs.toPrivacySettings()
        assertEquals(1f, settings.strength, 0f)
        assertEquals(PrivacySettings.MIN_CLEAR_AREA, settings.clearAreaWidth, 0f)
        assertEquals(PrivacySettings.MAX_GRADIENT_WIDTH, settings.gradientWidth, 0f)
    }
}
