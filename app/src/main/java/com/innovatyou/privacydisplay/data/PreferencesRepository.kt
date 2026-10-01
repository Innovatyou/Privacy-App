package com.innovatyou.privacydisplay.data

import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.MutablePreferences
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.booleanPreferencesKey
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.emptyPreferences
import androidx.datastore.preferences.core.floatPreferencesKey
import androidx.datastore.preferences.core.intPreferencesKey
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.core.stringSetPreferencesKey
import java.io.IOException
import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.catch
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.map

/** Source of truth for [PrivacySettings]. Settings are only ever stored locally. */
interface PreferencesRepository {
    val settings: Flow<PrivacySettings>

    /** Atomically applies [transform] to the stored settings. */
    suspend fun update(transform: (PrivacySettings) -> PrivacySettings)
}

@Singleton
class DataStorePreferencesRepository @Inject constructor(
    private val dataStore: DataStore<Preferences>,
) : PreferencesRepository {

    override val settings: Flow<PrivacySettings> = dataStore.data
        .catch { e -> if (e is IOException) emit(emptyPreferences()) else throw e }
        .map { it.toPrivacySettings() }
        .distinctUntilChanged()

    override suspend fun update(transform: (PrivacySettings) -> PrivacySettings) {
        dataStore.edit { prefs ->
            prefs.writePrivacySettings(transform(prefs.toPrivacySettings()).sanitized())
        }
    }
}

internal object PreferenceKeys {
    val ENABLED = booleanPreferencesKey("enabled")
    val STRENGTH = floatPreferencesKey("strength")
    val CLEAR_WIDTH = floatPreferencesKey("clear_area_width")
    val CLEAR_HEIGHT = floatPreferencesKey("clear_area_height")
    val EDGE_OPACITY = floatPreferencesKey("edge_opacity")
    val FACE_DETECTION = booleanPreferencesKey("face_detection")
    val MULTIPLE_VIEWERS = booleanPreferencesKey("multiple_viewer_protection")
    val MASK_MODE = stringPreferencesKey("mask_mode")
    val GRADIENT_WIDTH = floatPreferencesKey("gradient_width")
    val STRONGEST_MASK = booleanPreferencesKey("strongest_mask_on_multiple_viewers")
    val AUTO_ENABLE_ON_UNLOCK = booleanPreferencesKey("auto_enable_on_unlock")
    val BATTERY_SAVER = booleanPreferencesKey("battery_saver")
    val THEME_MODE = stringPreferencesKey("theme_mode")
    val EXCLUDED_APPS = stringSetPreferencesKey("excluded_apps")
    val BLUR_ON_EXTRA_VIEWER = booleanPreferencesKey("blur_on_extra_viewer")
    val BLUR_WHEN_AWAY = booleanPreferencesKey("blur_when_away")
    val BLUR_STRENGTH = floatPreferencesKey("blur_strength")
    val SHARE_MINUTES = intPreferencesKey("share_minutes")
}

internal fun Preferences.toPrivacySettings(): PrivacySettings {
    val d = PrivacySettings()
    return PrivacySettings(
        enabled = this[PreferenceKeys.ENABLED] ?: d.enabled,
        strength = this[PreferenceKeys.STRENGTH] ?: d.strength,
        clearAreaWidth = this[PreferenceKeys.CLEAR_WIDTH] ?: d.clearAreaWidth,
        clearAreaHeight = this[PreferenceKeys.CLEAR_HEIGHT] ?: d.clearAreaHeight,
        edgeOpacity = this[PreferenceKeys.EDGE_OPACITY] ?: d.edgeOpacity,
        faceDetectionEnabled = this[PreferenceKeys.FACE_DETECTION] ?: d.faceDetectionEnabled,
        multipleViewerProtection = this[PreferenceKeys.MULTIPLE_VIEWERS] ?: d.multipleViewerProtection,
        maskMode = enumOrDefault(this[PreferenceKeys.MASK_MODE], d.maskMode),
        gradientWidth = this[PreferenceKeys.GRADIENT_WIDTH] ?: d.gradientWidth,
        strongestMaskOnMultipleViewers = this[PreferenceKeys.STRONGEST_MASK] ?: d.strongestMaskOnMultipleViewers,
        autoEnableOnUnlock = this[PreferenceKeys.AUTO_ENABLE_ON_UNLOCK] ?: d.autoEnableOnUnlock,
        batterySaver = this[PreferenceKeys.BATTERY_SAVER] ?: d.batterySaver,
        themeMode = enumOrDefault(this[PreferenceKeys.THEME_MODE], d.themeMode),
        excludedApps = this[PreferenceKeys.EXCLUDED_APPS] ?: d.excludedApps,
        blurOnExtraViewer = this[PreferenceKeys.BLUR_ON_EXTRA_VIEWER] ?: d.blurOnExtraViewer,
        blurWhenAway = this[PreferenceKeys.BLUR_WHEN_AWAY] ?: d.blurWhenAway,
        blurStrength = this[PreferenceKeys.BLUR_STRENGTH] ?: d.blurStrength,
        shareMinutes = this[PreferenceKeys.SHARE_MINUTES] ?: d.shareMinutes,
    ).sanitized()
}

internal fun MutablePreferences.writePrivacySettings(s: PrivacySettings) {
    this[PreferenceKeys.ENABLED] = s.enabled
    this[PreferenceKeys.STRENGTH] = s.strength
    this[PreferenceKeys.CLEAR_WIDTH] = s.clearAreaWidth
    this[PreferenceKeys.CLEAR_HEIGHT] = s.clearAreaHeight
    this[PreferenceKeys.EDGE_OPACITY] = s.edgeOpacity
    this[PreferenceKeys.FACE_DETECTION] = s.faceDetectionEnabled
    this[PreferenceKeys.MULTIPLE_VIEWERS] = s.multipleViewerProtection
    this[PreferenceKeys.MASK_MODE] = s.maskMode.name
    this[PreferenceKeys.GRADIENT_WIDTH] = s.gradientWidth
    this[PreferenceKeys.STRONGEST_MASK] = s.strongestMaskOnMultipleViewers
    this[PreferenceKeys.AUTO_ENABLE_ON_UNLOCK] = s.autoEnableOnUnlock
    this[PreferenceKeys.BATTERY_SAVER] = s.batterySaver
    this[PreferenceKeys.THEME_MODE] = s.themeMode.name
    this[PreferenceKeys.EXCLUDED_APPS] = s.excludedApps
    this[PreferenceKeys.BLUR_ON_EXTRA_VIEWER] = s.blurOnExtraViewer
    this[PreferenceKeys.BLUR_WHEN_AWAY] = s.blurWhenAway
    this[PreferenceKeys.BLUR_STRENGTH] = s.blurStrength
    this[PreferenceKeys.SHARE_MINUTES] = s.shareMinutes
}

private inline fun <reified T : Enum<T>> enumOrDefault(name: String?, default: T): T =
    name?.let { n -> enumValues<T>().firstOrNull { it.name == n } } ?: default
