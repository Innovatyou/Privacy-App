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
)

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
}
