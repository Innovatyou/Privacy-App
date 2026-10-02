package com.innovatyou.privacydisplay.overlay

import com.innovatyou.privacydisplay.camera.ViewerState
import com.innovatyou.privacydisplay.data.MaskMode
import com.innovatyou.privacydisplay.data.PrivacySettings
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class PrivacyPolicyTest {

    private val settings = PrivacySettings(
        strength = 0.5f,
        edgeOpacity = 0.7f,
        maskMode = MaskMode.DARK_EDGES,
        faceDetectionEnabled = true,
        multipleViewerProtection = true,
        strongestMaskOnMultipleViewers = true,
    )

    @Test
    fun `without face detection the settings are used as-is`() {
        val off = settings.copy(faceDetectionEnabled = false)
        for (viewer in ViewerState.entries) {
            val mask = PrivacyPolicy.maskParams(off, viewer)
            assertEquals(0.5f, mask.strength, 0f)
            assertEquals(MaskMode.DARK_EDGES, mask.mode)
            assertFalse(mask.boosted)
        }
    }

    @Test
    fun `multiple viewers switch to the strongest mask`() {
        val mask = PrivacyPolicy.maskParams(settings, ViewerState.MULTIPLE_VIEWERS)
        assertEquals(MaskMode.NARROW_WINDOW, mask.mode)
        assertEquals(1f, mask.strength, 0f)
        assertEquals(1f, mask.edgeOpacity, 0f)
        assertTrue(mask.boosted)
    }

    @Test
    fun `multiple viewers keep the mask style when the strongest mask is off`() {
        val mask = PrivacyPolicy.maskParams(
            settings.copy(strongestMaskOnMultipleViewers = false), ViewerState.MULTIPLE_VIEWERS
        )
        assertEquals(MaskMode.DARK_EDGES, mask.mode)
        assertEquals(1f, mask.strength, 0f)
    }

    @Test
    fun `multiple viewers are ignored when protection is off`() {
        val mask = PrivacyPolicy.maskParams(
            settings.copy(multipleViewerProtection = false), ViewerState.MULTIPLE_VIEWERS
        )
        assertEquals(0.5f, mask.strength, 0f)
        assertFalse(mask.boosted)
    }

    @Test
    fun `no face or looking away raises the strength`() {
        for (viewer in listOf(ViewerState.NO_FACE, ViewerState.LOOKING_AWAY)) {
            val mask = PrivacyPolicy.maskParams(settings, viewer)
            assertEquals(PrivacyPolicy.NO_VIEWER_STRENGTH, mask.strength, 0f)
            assertTrue(mask.boosted)
        }
    }

    @Test
    fun `a strong mask is not reported as boosted`() {
        val mask = PrivacyPolicy.maskParams(settings.copy(strength = 0.95f), ViewerState.NO_FACE)
        assertEquals(0.95f, mask.strength, 0f)
        assertFalse(mask.boosted)
    }

    @Test
    fun `shield turns on for an extra viewer`() {
        val on = settings.copy(enabled = true)
        assertEquals(ShieldReason.EXTRA_VIEWER, PrivacyPolicy.shieldReason(on, ViewerState.MULTIPLE_VIEWERS, testing = false))
        assertEquals(null, PrivacyPolicy.shieldReason(on, ViewerState.USER_PRESENT, testing = false))
        assertEquals(null, PrivacyPolicy.shieldReason(on.copy(blurOnExtraViewer = false), ViewerState.MULTIPLE_VIEWERS, false))
        assertEquals(null, PrivacyPolicy.shieldReason(on.copy(multipleViewerProtection = false), ViewerState.MULTIPLE_VIEWERS, false))
        assertEquals(null, PrivacyPolicy.shieldReason(on.copy(faceDetectionEnabled = false), ViewerState.MULTIPLE_VIEWERS, false))
    }

    @Test
    fun `shield when nobody is looking is opt in`() {
        val on = settings.copy(enabled = true)
        assertEquals(null, PrivacyPolicy.shieldReason(on, ViewerState.NO_FACE, testing = false))
        assertEquals(
            ShieldReason.NOBODY_LOOKING,
            PrivacyPolicy.shieldReason(on.copy(blurWhenAway = true), ViewerState.LOOKING_AWAY, testing = false),
        )
    }

    @Test
    fun `shield test works only while privacy mode is on`() {
        assertEquals(ShieldReason.TEST, PrivacyPolicy.shieldReason(settings.copy(enabled = true), ViewerState.OFF, testing = true))
        assertEquals(null, PrivacyPolicy.shieldReason(settings.copy(enabled = false), ViewerState.OFF, testing = true))
    }

    @Test
    fun `without system blur the shield darkens the whole screen`() {
        val mask = PrivacyPolicy.maskParams(settings, ViewerState.MULTIPLE_VIEWERS)
        assertTrue(PrivacyPolicy.shieldedMask(mask, ShieldReason.EXTRA_VIEWER, blurAvailable = false).fullCover)
        assertFalse(PrivacyPolicy.shieldedMask(mask, ShieldReason.EXTRA_VIEWER, blurAvailable = true).fullCover)
        assertFalse(PrivacyPolicy.shieldedMask(mask, null, blurAvailable = false).fullCover)
    }

    @Test
    fun `half strength gives a visibly partial shield`() {
        val low = PrivacyPolicy.shieldVeilAlpha(0f)
        val half = PrivacyPolicy.shieldVeilAlpha(0.5f)
        val full = PrivacyPolicy.shieldVeilAlpha(1f)
        assertEquals((low + full) / 2f, half, 0.0001f)
        assertTrue(PrivacyPolicy.blurRadiusDp(0.5f) > PrivacyPolicy.blurRadiusDp(0f))
        assertTrue(PrivacyPolicy.blurRadiusDp(0.5f) < PrivacyPolicy.blurRadiusDp(1f))
    }

    @Test
    fun `sharing is offered while someone else is looking`() {
        val on = settings.copy(enabled = true)
        assertTrue(PrivacyPolicy.offerSharing(on, ViewerState.MULTIPLE_VIEWERS, ShieldReason.EXTRA_VIEWER))
        assertTrue(PrivacyPolicy.offerSharing(on, ViewerState.OFF, ShieldReason.TEST))
        assertTrue(PrivacyPolicy.offerSharing(on.copy(blurOnExtraViewer = false), ViewerState.MULTIPLE_VIEWERS, null))
        assertFalse(PrivacyPolicy.offerSharing(on, ViewerState.USER_PRESENT, null))
        // Any automatic blur can be dismissed with the floating button.
        assertTrue(PrivacyPolicy.offerSharing(on, ViewerState.NO_FACE, ShieldReason.NOBODY_LOOKING))
        assertFalse(PrivacyPolicy.offerSharing(on, ViewerState.NO_FACE, null))
    }

    @Test
    fun `blur radius grows with strength`() {
        assertTrue(PrivacyPolicy.blurRadiusDp(1f) > PrivacyPolicy.blurRadiusDp(0f))
        assertEquals(PrivacyPolicy.blurRadiusDp(1f), PrivacyPolicy.blurRadiusDp(5f), 0f)
    }

    @Test
    fun `darkness is not treated as nobody looking`() {
        val dark = PrivacyPolicy.maskParams(settings, ViewerState.TOO_DARK)
        assertEquals(0.5f, dark.strength, 0f)
        assertFalse(dark.boosted)
        val on = settings.copy(enabled = true, blurWhenAway = true)
        assertEquals(null, PrivacyPolicy.shieldReason(on, ViewerState.TOO_DARK, testing = false))
    }

    @Test
    fun `darkness counts as nobody looking only when pausing in the dark is off`() {
        val strict = settings.copy(pauseEffectsInDark = false)
        assertEquals(PrivacyPolicy.NO_VIEWER_STRENGTH, PrivacyPolicy.maskParams(strict, ViewerState.TOO_DARK).strength, 0f)
        assertEquals(
            ShieldReason.NOBODY_LOOKING,
            PrivacyPolicy.shieldReason(strict.copy(enabled = true, blurWhenAway = true), ViewerState.TOO_DARK, false),
        )
    }

    @Test
    fun `darkening when nobody looks can be switched off`() {
        val off = settings.copy(darkenWhenNobodyLooking = false)
        for (viewer in listOf(ViewerState.NO_FACE, ViewerState.LOOKING_AWAY)) {
            val mask = PrivacyPolicy.maskParams(off, viewer)
            assertEquals(0.5f, mask.strength, 0f)
            assertFalse(mask.boosted)
        }
    }

    @Test
    fun `camera problems never change the mask`() {
        for (viewer in listOf(
            ViewerState.USER_PRESENT, ViewerState.UNAVAILABLE, ViewerState.BLOCKED_IN_BACKGROUND,
            ViewerState.PAUSED_BATTERY, ViewerState.STARTING, ViewerState.OFF,
        )) {
            assertEquals(0.5f, PrivacyPolicy.maskParams(settings, viewer).strength, 0f)
        }
    }
}
