package com.innovatyou.privacydisplay.owner

/**
 * Collects the owner's face samples during setup: a few looking straight at the camera and a few
 * turned slightly left and right, so the owner is recognised from small angles too.
 */
class EnrollmentCollector(
    private val straightSamples: Int = 3,
    private val sideSamples: Int = 2,
) {
    enum class Pose { STRAIGHT, LEFT, RIGHT }

    private val samples = mutableMapOf<Pose, MutableList<FloatArray>>()

    val total: Int get() = straightSamples + 2 * sideSamples
    val collected: Int get() = samples.values.sumOf { it.size }
    val isComplete: Boolean get() = collected >= total

    /** The pose still needed next, or null when done. */
    val nextPose: Pose?
        get() = when {
            count(Pose.STRAIGHT) < straightSamples -> Pose.STRAIGHT
            count(Pose.LEFT) < sideSamples -> Pose.LEFT
            count(Pose.RIGHT) < sideSamples -> Pose.RIGHT
            else -> null
        }

    /** Adds a sample if its head angle matches the pose still needed. Returns true if accepted. */
    fun offer(yawDegrees: Float, embedding: FloatArray): Boolean {
        val pose = nextPose ?: return false
        if (poseOf(yawDegrees) != pose) return false
        samples.getOrPut(pose) { mutableListOf() } += embedding
        return true
    }

    fun embeddings(): List<FloatArray> = samples.values.flatten()

    private fun count(pose: Pose) = samples[pose]?.size ?: 0

    companion object {
        fun poseOf(yawDegrees: Float): Pose? = when {
            yawDegrees in -10f..10f -> Pose.STRAIGHT
            yawDegrees in 12f..35f -> Pose.LEFT
            yawDegrees in -35f..-12f -> Pose.RIGHT
            else -> null
        }
    }
}
