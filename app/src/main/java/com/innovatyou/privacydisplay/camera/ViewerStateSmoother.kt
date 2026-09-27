package com.innovatyou.privacydisplay.camera

/**
 * Turns noisy per-frame observations into a stable [ViewerState], so that the mask does not
 * flicker when a face is missed for a frame or two.
 */
class ViewerStateSmoother(
    /** How long no face must be seen before reporting [ViewerState.NO_FACE]. */
    private val noFaceGraceMs: Long = 2_500,
    /** Consecutive frames with 2+ faces needed before reporting [ViewerState.MULTIPLE_VIEWERS]. */
    private val multipleConfirmations: Int = 2,
    /** How long [ViewerState.MULTIPLE_VIEWERS] is held after the extra face disappears. */
    private val multipleReleaseMs: Long = 3_000,
) {
    var state: ViewerState = ViewerState.STARTING
        private set

    private var startedAt = NEVER
    private var lastFaceAt = NEVER
    private var lastMultipleAt = NEVER
    private var multipleStreak = 0

    fun update(observation: FaceObservation, nowMs: Long): ViewerState {
        if (startedAt == NEVER) startedAt = nowMs
        if (observation.faceCount >= 1) lastFaceAt = nowMs
        if (observation.faceCount >= 2) {
            multipleStreak++
            lastMultipleAt = nowMs
        } else {
            multipleStreak = 0
        }

        val sinceFace = nowMs - (if (lastFaceAt != NEVER) lastFaceAt else startedAt)
        state = when {
            multipleStreak >= multipleConfirmations -> ViewerState.MULTIPLE_VIEWERS
            state == ViewerState.MULTIPLE_VIEWERS && nowMs - lastMultipleAt < multipleReleaseMs ->
                ViewerState.MULTIPLE_VIEWERS
            observation.faceCount >= 1 ->
                if (observation.primaryFacing) ViewerState.USER_PRESENT else ViewerState.LOOKING_AWAY
            sinceFace < noFaceGraceMs -> when (state) {
                ViewerState.MULTIPLE_VIEWERS, ViewerState.NO_FACE -> ViewerState.USER_PRESENT
                else -> state
            }
            else -> ViewerState.NO_FACE
        }
        return state
    }

    private companion object {
        const val NEVER = Long.MIN_VALUE
    }
}
