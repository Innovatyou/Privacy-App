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
    fun `blur radius grows with strength`() {
        assertTrue(PrivacyPolicy.blurRadiusDp(1f) > PrivacyPolicy.blurRadiusDp(0f))
        assertEquals(PrivacyPolicy.blurRadiusDp(1f), PrivacyPolicy.blurRadiusDp(5f), 0f)
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
