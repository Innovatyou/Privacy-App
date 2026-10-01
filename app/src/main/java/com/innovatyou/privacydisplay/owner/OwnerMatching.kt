package com.innovatyou.privacydisplay.owner

import kotlin.math.sqrt

/** Result of comparing one camera frame with the owner's face print. */
enum class OwnerCheck { OWNER, STRANGER, UNCLEAR }

/** Smoothed decision used to lock and unlock the phone. */
enum class OwnerDecision { OWNER, STRANGER, UNKNOWN }

object OwnerMatching {
    /**
     * Cosine similarity above which a face counts as the owner. OpenCV recommends 0.363 for SFace;
     * a slightly stricter value is used because a false "owner" is worse than a false "stranger"
     * (which the owner can clear with a fingerprint or PIN).
     */
    const val OWNER_THRESHOLD = 0.40f

    fun normalize(v: FloatArray): FloatArray {
        var sum = 0.0
        for (x in v) sum += x * x
        val norm = sqrt(sum).toFloat()
        return if (norm == 0f) v.copyOf() else FloatArray(v.size) { v[it] / norm }
    }

    /** Cosine similarity of two L2-normalized vectors. */
    fun similarity(a: FloatArray, b: FloatArray): Float {
        require(a.size == b.size)
        var dot = 0f
        for (i in a.indices) dot += a[i] * b[i]
        return dot
    }

    /** Best similarity of [face] against any of the owner's samples. */
    fun bestSimilarity(face: FloatArray, owner: List<FloatArray>): Float =
        owner.maxOfOrNull { similarity(face, it) } ?: -1f

    fun check(face: FloatArray, owner: List<FloatArray>): OwnerCheck =
        if (bestSimilarity(face, owner) >= OWNER_THRESHOLD) OwnerCheck.OWNER else OwnerCheck.STRANGER

    /** Recognition only runs on faces that are large and frontal enough to be reliable. */
    fun isUsableFace(faceWidthFraction: Float, yawDegrees: Float, pitchDegrees: Float): Boolean =
        faceWidthFraction >= MIN_FACE_WIDTH && kotlin.math.abs(yawDegrees) <= MAX_ANGLE &&
            kotlin.math.abs(pitchDegrees) <= MAX_ANGLE

    private const val MIN_FACE_WIDTH = 0.15f
    private const val MAX_ANGLE = 30f
}

/**
 * Smooths per-frame checks: one clear owner match is enough to recognise the owner, while a
 * stranger must be seen in [strangerConfirmations] checks in a row before the phone locks.
 */
class OwnerVerifier(private val strangerConfirmations: Int = 2) {
    private var strangerStreak = 0
    var decision: OwnerDecision = OwnerDecision.UNKNOWN
        private set

    fun update(check: OwnerCheck): OwnerDecision {
        decision = when (check) {
            OwnerCheck.OWNER -> {
                strangerStreak = 0
                OwnerDecision.OWNER
            }
            OwnerCheck.STRANGER -> {
                strangerStreak++
                if (strangerStreak >= strangerConfirmations) OwnerDecision.STRANGER else decision
            }
            OwnerCheck.UNCLEAR -> decision
        }
        return decision
    }
}
