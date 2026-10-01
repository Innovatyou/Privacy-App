package com.innovatyou.privacydisplay.overlay

import com.innovatyou.privacydisplay.camera.ViewerState
import com.innovatyou.privacydisplay.data.MaskMode
import com.innovatyou.privacydisplay.data.PrivacySettings

/** The mask to draw right now: the user's settings plus any automatic protection boost. */
data class MaskParams(
    val mode: MaskMode,
    val strength: Float,
    val clearAreaWidth: Float,
    val clearAreaHeight: Float,
    val edgeOpacity: Float,
    val gradientWidth: Float,
    /** True when face detection raised the protection above the user's settings. */
    val boosted: Boolean = false,
    /** Cover the whole screen (used by the viewer shield when system blur is unavailable). */
    val fullCover: Boolean = false,
)

/** Why the viewer shield (full-screen blur) is active. */
enum class ShieldReason { EXTRA_VIEWER, NOBODY_LOOKING, TEST }

/** Decides how face detection changes the mask. */
object PrivacyPolicy {
    /** Minimum strength while nobody (or only someone looking away) is in front of the screen. */
    const val NO_VIEWER_STRENGTH = 0.9f

    fun maskParams(settings: PrivacySettings, viewer: ViewerState): MaskParams {
        val base = MaskParams(
            mode = settings.maskMode,
            strength = settings.strength,
            clearAreaWidth = settings.clearAreaWidth,
            clearAreaHeight = settings.clearAreaHeight,
            edgeOpacity = settings.edgeOpacity,
            gradientWidth = settings.gradientWidth,
        )
        if (!settings.faceDetectionEnabled) return base

        return when (viewer) {
            ViewerState.MULTIPLE_VIEWERS ->
                if (!settings.multipleViewerProtection) {
                    base
                } else {
                    base.copy(
                        mode = if (settings.strongestMaskOnMultipleViewers) MaskMode.NARROW_WINDOW else base.mode,
                        strength = 1f,
                        edgeOpacity = 1f,
                        boosted = true,
                    )
                }
            ViewerState.NO_FACE, ViewerState.LOOKING_AWAY ->
                if (base.strength >= NO_VIEWER_STRENGTH) {
                    base
                } else {
                    base.copy(strength = NO_VIEWER_STRENGTH, boosted = true)
                }
            else -> base
        }
    }

    /**
     * Whether the whole screen should be shielded (blurred) right now.
     * @param testing true while the user is previewing the shield from settings.
     */
    fun shieldReason(settings: PrivacySettings, viewer: ViewerState, testing: Boolean): ShieldReason? {
        if (!settings.enabled) return null
        if (testing) return ShieldReason.TEST
        if (!settings.faceDetectionEnabled) return null
        return when {
            viewer == ViewerState.MULTIPLE_VIEWERS &&
                settings.multipleViewerProtection && settings.blurOnExtraViewer -> ShieldReason.EXTRA_VIEWER
            (viewer == ViewerState.NO_FACE || viewer == ViewerState.LOOKING_AWAY) &&
                settings.blurWhenAway -> ShieldReason.NOBODY_LOOKING
            else -> null
        }
    }

    /**
     * The mask to draw together with the shield. With system blur the normal mask stays; without
     * it (Android 11 and older, or blur turned off by the system) the screen is fully darkened.
     */
    fun shieldedMask(mask: MaskParams, shield: ShieldReason?, blurAvailable: Boolean): MaskParams =
        if (shield != null && !blurAvailable) mask.copy(fullCover = true, boosted = true) else mask

    /** Blur radius in dp for a 0–1 strength. */
    fun blurRadiusDp(strength: Float): Float = MIN_BLUR_DP + (MAX_BLUR_DP - MIN_BLUR_DP) * strength.coerceIn(0f, 1f)

    /**
     * Opacity of the frosted veil drawn with the blur. It follows the strength linearly, so the
     * shield gets visibly stronger step by step even on phones whose system blur is not linear.
     */
    fun shieldVeilAlpha(strength: Float): Float =
        MIN_VEIL + (MAX_VEIL - MIN_VEIL) * strength.coerceIn(0f, 1f)

    /** Whether to offer the "Share screen" button: someone else is looking (or the user is testing). */
    fun offerSharing(settings: PrivacySettings, viewer: ViewerState, shield: ShieldReason?): Boolean =
        shield == ShieldReason.TEST || shield == ShieldReason.EXTRA_VIEWER ||
            (settings.faceDetectionEnabled && settings.multipleViewerProtection && viewer == ViewerState.MULTIPLE_VIEWERS)

    private const val MIN_BLUR_DP = 2f
    private const val MAX_BLUR_DP = 80f
    private const val MIN_VEIL = 0.05f
    private const val MAX_VEIL = 0.55f
}
