package com.innovatyou.privacydisplay.owner

import com.innovatyou.privacydisplay.data.PrivacySettings
import org.junit.Assert.assertArrayEquals
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class OwnerLogicTest {

    private fun unit(vararg v: Float) = OwnerMatching.normalize(v)

    @Test
    fun `similar faces count as the owner and different ones do not`() {
        val owner = listOf(unit(1f, 0f, 0f), unit(0.9f, 0.1f, 0f))
        assertEquals(OwnerCheck.OWNER, OwnerMatching.check(unit(1f, 0.05f, 0f), owner))
        assertEquals(OwnerCheck.STRANGER, OwnerMatching.check(unit(0f, 0f, 1f), owner))
        assertEquals(OwnerCheck.STRANGER, OwnerMatching.check(unit(1f, 0f, 0f), emptyList()))
    }

    @Test
    fun `only large frontal faces are used`() {
        assertTrue(OwnerMatching.isUsableFace(0.3f, 5f, -5f))
        assertFalse(OwnerMatching.isUsableFace(0.1f, 0f, 0f))
        assertFalse(OwnerMatching.isUsableFace(0.3f, 45f, 0f))
    }

    @Test
    fun `a stranger must be seen twice in a row`() {
        val verifier = OwnerVerifier(strangerConfirmations = 2)
        assertEquals(OwnerDecision.UNKNOWN, verifier.update(OwnerCheck.STRANGER))
        assertEquals(OwnerDecision.STRANGER, verifier.update(OwnerCheck.STRANGER))
        assertEquals(OwnerDecision.STRANGER, verifier.update(OwnerCheck.UNCLEAR))
        assertEquals(OwnerDecision.OWNER, verifier.update(OwnerCheck.OWNER))
        assertEquals(OwnerDecision.OWNER, verifier.update(OwnerCheck.STRANGER))
    }

    @Test
    fun `locking follows the decision unless the owner unlocked recently`() {
        assertTrue(OwnerPolicy.nextLocked(false, OwnerDecision.STRANGER, ownerTrusted = false))
        assertFalse(OwnerPolicy.nextLocked(false, OwnerDecision.STRANGER, ownerTrusted = true))
        assertFalse(OwnerPolicy.nextLocked(true, OwnerDecision.OWNER, ownerTrusted = false))
        assertTrue(OwnerPolicy.nextLocked(true, OwnerDecision.UNKNOWN, ownerTrusted = false))
    }

    @Test
    fun `darkness alone never blocks unless strict mode is on`() {
        assertFalse(OwnerPolicy.nextLocked(false, OwnerDecision.TOO_DARK, ownerTrusted = false, blockWhenTooDark = false))
        assertTrue(OwnerPolicy.nextLocked(false, OwnerDecision.TOO_DARK, ownerTrusted = false, blockWhenTooDark = true))
        assertFalse(OwnerPolicy.nextLocked(false, OwnerDecision.TOO_DARK, ownerTrusted = true, blockWhenTooDark = true))
        assertTrue(OwnerPolicy.nextLocked(true, OwnerDecision.TOO_DARK, ownerTrusted = false, blockWhenTooDark = false))
    }

    @Test
    fun `the owner's face unblocks only after a blink`() {
        assertTrue(OwnerPolicy.nextLocked(true, OwnerDecision.OWNER, ownerTrusted = false, blinked = false))
        assertFalse(OwnerPolicy.nextLocked(true, OwnerDecision.OWNER, ownerTrusted = false, blinked = true))
        // Not blocked: no blink needed to stay unblocked.
        assertFalse(OwnerPolicy.nextLocked(false, OwnerDecision.OWNER, ownerTrusted = false, blinked = false))
    }

    @Test
    fun `darkness needs several checks and does not erase a decision`() {
        val verifier = OwnerVerifier(strangerConfirmations = 2, darkConfirmations = 3)
        assertEquals(OwnerDecision.OWNER, verifier.update(OwnerCheck.OWNER))
        assertEquals(OwnerDecision.OWNER, verifier.update(OwnerCheck.TOO_DARK))
        assertEquals(OwnerDecision.OWNER, verifier.update(OwnerCheck.TOO_DARK))
        assertEquals(OwnerDecision.TOO_DARK, verifier.update(OwnerCheck.TOO_DARK))
        assertEquals(OwnerDecision.OWNER, verifier.update(OwnerCheck.OWNER))
    }

    @Test
    fun `block screen hides while sharing or unlocking`() {
        assertTrue(OwnerPolicy.showBlockScreen(active = true, locked = true, sharing = false, unlockScreenOpen = false))
        assertFalse(OwnerPolicy.showBlockScreen(active = true, locked = true, sharing = true, unlockScreenOpen = false))
        assertFalse(OwnerPolicy.showBlockScreen(active = true, locked = true, sharing = false, unlockScreenOpen = true))
        assertFalse(OwnerPolicy.showBlockScreen(active = false, locked = true, sharing = false, unlockScreenOpen = false))
    }

    @Test
    fun `protection is active only with privacy on and a face set up`() {
        val on = PrivacySettings(enabled = true, ownerProtection = true)
        assertTrue(OwnerPolicy.isActive(on, enrolled = true))
        assertFalse(OwnerPolicy.isActive(on, enrolled = false))
        assertFalse(OwnerPolicy.isActive(on.copy(enabled = false), enrolled = true))
    }

    @Test
    fun `setup collects straight and side samples in order`() {
        val collector = EnrollmentCollector(straightSamples = 2, sideSamples = 1)
        val e = FloatArray(4)
        assertEquals(EnrollmentCollector.Pose.STRAIGHT, collector.nextPose)
        assertFalse(collector.offer(20f, e)) // Side pose not wanted yet.
        assertTrue(collector.offer(0f, e))
        assertTrue(collector.offer(3f, e))
        assertEquals(EnrollmentCollector.Pose.LEFT, collector.nextPose)
        assertTrue(collector.offer(20f, e))
        assertTrue(collector.offer(-20f, e))
        assertTrue(collector.isComplete)
        assertEquals(4, collector.embeddings().size)
        assertFalse(collector.offer(0f, e))
    }

    @Test
    fun `face print survives encoding`() {
        val prints = listOf(floatArrayOf(0.1f, -0.2f, 0.3f), floatArrayOf(1f, 2f, 3f))
        val decoded = FacePrintCodec.decode(FacePrintCodec.encode(prints))
        assertEquals(2, decoded.size)
        assertArrayEquals(prints[0], decoded[0], 0f)
        assertArrayEquals(prints[1], decoded[1], 0f)
    }
}
