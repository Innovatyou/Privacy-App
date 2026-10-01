package com.innovatyou.privacydisplay.data

/** How the privacy mask is drawn. */
enum class MaskMode {
    /** Solid black outside the clear area, with a hard edge. */
    BLACK,

    /** Soft darkening that fades in toward the screen edges. */
    DARK_EDGES,

    /** A smaller clear window with solid black around it. The strongest mask. */
    NARROW_WINDOW,

    /** An oval clear area with a radial gradient to the edges. */
    GRADIENT,

    /** Grainy, frosted-glass fog toward the edges instead of plain darkening. */
    FROSTED,

    /** Edge opacity is used as-is and privacy strength also dims the clear area. */
    CUSTOM,
}

enum class ThemeMode { SYSTEM, LIGHT, DARK }

/**
 * All user settings. Fractions and opacities are in the range 0.0–1.0; the UI shows them as
 * percentages.
 */
data class PrivacySettings(
    val enabled: Boolean = false,
    /** Overall darkness of the mask. */
    val strength: Float = 0.75f,
    /** Width of the clear area as a fraction of the screen width (portrait). */
    val clearAreaWidth: Float = 0.85f,
    /** Height of the clear area as a fraction of the screen height (portrait). */
    val clearAreaHeight: Float = 0.45f,
    /** Maximum opacity at the screen edges for the soft masks. */
    val edgeOpacity: Float = 0.9f,
    val faceDetectionEnabled: Boolean = false,
    val multipleViewerProtection: Boolean = false,
    val maskMode: MaskMode = MaskMode.DARK_EDGES,
    /** Width of the soft edge as a fraction of the shorter screen side. */
    val gradientWidth: Float = 0.12f,
    val strongestMaskOnMultipleViewers: Boolean = true,
    val autoEnableOnUnlock: Boolean = false,
    val batterySaver: Boolean = true,
    val themeMode: ThemeMode = ThemeMode.SYSTEM,
    /** Package names of apps in which the mask is paused ("Apps to ignore"). */
    val excludedApps: Set<String> = RecommendedExclusions.PACKAGES,
    /** Blur the whole screen while face detection sees an additional viewer. */
    val blurOnExtraViewer: Boolean = true,
    /** Blur the whole screen while nobody is looking at it (no face, or looking away). */
    val blurWhenAway: Boolean = false,
    /** Strength of the viewer-shield blur. */
    val blurStrength: Float = 0.6f,
    /** How long "Share screen" turns privacy off for, in minutes. */
    val shareMinutes: Int = DEFAULT_SHARE_MINUTES,
    /** Block the phone for anyone whose face is not the owner's (needs a set-up face print). */
    val ownerProtection: Boolean = false,
) {
    /** Returns a copy with every numeric value clamped to its valid range. */
    fun sanitized(): PrivacySettings = copy(
        strength = strength.coerceIn(0f, 1f),
        clearAreaWidth = clearAreaWidth.coerceIn(MIN_CLEAR_AREA, 1f),
        clearAreaHeight = clearAreaHeight.coerceIn(MIN_CLEAR_AREA, 1f),
        edgeOpacity = edgeOpacity.coerceIn(0f, 1f),
        gradientWidth = gradientWidth.coerceIn(0f, MAX_GRADIENT_WIDTH),
        blurStrength = blurStrength.coerceIn(0f, 1f),
        shareMinutes = shareMinutes.takeIf { it in SHARE_MINUTE_OPTIONS } ?: DEFAULT_SHARE_MINUTES,
    )

    /** Whether the overlay service needs to keep running (active or waiting for an unlock). */
    val needsService: Boolean get() = enabled || autoEnableOnUnlock

    companion object {
        const val MIN_CLEAR_AREA = 0.1f
        const val MAX_GRADIENT_WIDTH = 0.5f
        const val DEFAULT_SHARE_MINUTES = 10
        val SHARE_MINUTE_OPTIONS = listOf(5, 10, 15, 30, 60)
    }
}
