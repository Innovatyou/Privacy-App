package com.innovatyou.privacydisplay.camera

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

    /** Two or more faces are visible. */
    MULTIPLE_VIEWERS,

    /** Paused because Battery Saver is on and battery-saving mode is enabled. */
    PAUSED_BATTERY,

    /** Android does not allow camera access from the background until the app is opened. */
    BLOCKED_IN_BACKGROUND,

    /** No front camera, no permission, or the camera is in use by another app. */
    UNAVAILABLE,
}

/** Result of analysing one camera frame. Contains no image data. */
data class FaceObservation(val faceCount: Int, val primaryFacing: Boolean)
