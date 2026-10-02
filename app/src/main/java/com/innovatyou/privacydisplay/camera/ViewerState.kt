package com.innovatyou.privacydisplay.camera

import com.innovatyou.privacydisplay.owner.OwnerDecision

/** What face detection currently believes about the people looking at the screen. */
enum class ViewerState {
    /** Face detection is off or not needed right now. */
    OFF,
    STARTING,

    /** One face is visible and looking at the screen. */
    USER_PRESENT,

    /** One face is visible but turned away from the screen. */
    LOOKING_AWAY,

    /** No face has been visible for a while. */
    NO_FACE,

    /** No face visible, but it is too dim for the camera to tell whether anyone is there. */
    TOO_DARK,

    /** Two or more faces are visible. */
    MULTIPLE_VIEWERS,

    /** Paused because Battery Saver is on and battery-saving mode is enabled. */
    PAUSED_BATTERY,

    /** Android does not allow camera access from the background until the app is opened. */
    BLOCKED_IN_BACKGROUND,

    /** No front camera, no permission, or the camera is in use by another app. */
    UNAVAILABLE,
}

/** Where an additional viewer is, as seen by the phone's user looking at the screen. */
enum class ViewerSide { LEFT, RIGHT, ABOVE, BELOW }

/** Result of analysing one camera frame. Contains no image data. */
data class FaceObservation(
    val faceCount: Int,
    val primaryFacing: Boolean,
    /** Side of the largest additional face relative to the main user, if there is one. */
    val extraViewerSide: ViewerSide? = null,
)

/** Smoothed face detection result reported to the service. */
data class ViewerReport(
    val state: ViewerState,
    /** Side of the additional viewer while [state] is [ViewerState.MULTIPLE_VIEWERS]. */
    val extraViewerSide: ViewerSide? = null,
    /** Whether the main face is the owner (only when owner protection is on). */
    val owner: OwnerDecision = OwnerDecision.UNKNOWN,
    /** The owner blinked recently (liveness check against photos). */
    val recentBlink: Boolean = false,
    /** The scene or face is dim: exposure is raised and the optional screen glow can help. */
    val lowLight: Boolean = false,
)

object ViewerGeometry {
    /**
     * Side of an additional face ([extraX], [extraY]) relative to the main user ([userX], [userY]),
     * in upright analysis-image coordinates of size [width] x [height].
     *
     * CameraX analysis frames from the front camera are not mirrored: the camera faces the user,
     * so a person on the user's left appears on the right of the image (larger x).
     */
    fun sideOf(
        extraX: Float,
        extraY: Float,
        userX: Float,
        userY: Float,
        width: Float,
        height: Float,
    ): ViewerSide {
        val dx = (extraX - userX) / width.coerceAtLeast(1f)
        val dy = (extraY - userY) / height.coerceAtLeast(1f)
        return if (kotlin.math.abs(dx) >= kotlin.math.abs(dy)) {
            if (dx > 0f) ViewerSide.LEFT else ViewerSide.RIGHT
        } else {
            if (dy < 0f) ViewerSide.ABOVE else ViewerSide.BELOW
        }
    }
}
