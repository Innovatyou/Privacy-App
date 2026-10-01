package com.innovatyou.privacydisplay.ui

import androidx.compose.foundation.layout.Box
import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.assertIsOff
import androidx.compose.ui.test.assertIsOn
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.compose.ui.test.performScrollTo
import androidx.test.ext.junit.runners.AndroidJUnit4
import androidx.test.platform.app.InstrumentationRegistry
import com.innovatyou.privacydisplay.R
import com.innovatyou.privacydisplay.data.PrivacySettings
import com.innovatyou.privacydisplay.owner.EnrollmentCollector
import com.innovatyou.privacydisplay.ui.theme.PrivacyDisplayTheme
import com.innovatyou.privacydisplay.util.PermissionState
import com.innovatyou.privacydisplay.viewmodel.FaceSetupHint
import com.innovatyou.privacydisplay.viewmodel.FaceSetupState
import com.innovatyou.privacydisplay.viewmodel.PrivacyUiState
import org.junit.Assert.assertEquals
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith

@RunWith(AndroidJUnit4::class)
class OwnerProtectionUiTest {

    @get:Rule
    val composeRule = createComposeRule()

    private val context = InstrumentationRegistry.getInstrumentation().targetContext

    private fun state(enrolled: Boolean, protection: Boolean) = PrivacyUiState(
        settings = PrivacySettings(enabled = true, faceDetectionEnabled = true, ownerProtection = protection),
        permissions = PermissionState(overlay = true, camera = true, notifications = true, usageAccess = true, screenLock = true),
        maxOverlayOpacity = 0.8f,
        loaded = true,
        ownerEnrolled = enrolled,
    )

    @Test
    fun withoutAFaceProtectionIsOffAndSetupIsOffered() {
        var setUp = false
        composeRule.setContent {
            PrivacyDisplayTheme {
                SettingsScreen(
                    state = state(enrolled = false, protection = false),
                    actions = PrivacyActions(onSetUpFace = { setUp = true }),
                    onBack = {},
                    onOpenExclusions = {},
                )
            }
        }
        composeRule.onNodeWithTag(OWNER_PROTECTION_TAG).performScrollTo().assertIsOff()
        composeRule.onNodeWithTag(SET_UP_FACE_TAG).performScrollTo().performClick()
        assertEquals(true, setUp)
    }

    @Test
    fun withAFaceProtectionShowsOnAndDeleteIsOffered() {
        var deleted = false
        composeRule.setContent {
            PrivacyDisplayTheme {
                SettingsScreen(
                    state = state(enrolled = true, protection = true),
                    actions = PrivacyActions(onDeleteFace = { deleted = true }),
                    onBack = {},
                    onOpenExclusions = {},
                )
            }
        }
        composeRule.onNodeWithTag(OWNER_PROTECTION_TAG).performScrollTo().assertIsOn()
        composeRule.onNodeWithText(context.getString(R.string.owner_delete)).performScrollTo().performClick()
        assertEquals(true, deleted)
    }

    @Test
    fun mainScreenOffersLendingWhenProtected() {
        var lent = false
        composeRule.setContent {
            PrivacyDisplayTheme {
                PrivacyDisplayScreen(
                    state = state(enrolled = true, protection = true),
                    actions = PrivacyActions(onStartSharing = { lent = true }),
                    onOpenSettings = {},
                )
            }
        }
        composeRule.onNodeWithTag(START_SHARING_TAG).performScrollTo().performClick()
        assertEquals(true, lent)
    }

    @Test
    fun faceSetupGuidesTheOwner() {
        composeRule.setContent {
            PrivacyDisplayTheme {
                FaceSetupScreen(
                    state = FaceSetupState(collected = 3, total = 7, pose = EnrollmentCollector.Pose.LEFT, hint = FaceSetupHint.FOLLOW_POSE),
                    onDone = {},
                    onBack = {},
                    camera = { modifier -> Box(modifier) },
                )
            }
        }
        composeRule.onNodeWithText(context.getString(R.string.face_setup_turn_one_side)).assertIsDisplayed()
        composeRule.onNodeWithText(context.getString(R.string.face_setup_privacy)).assertIsDisplayed()
    }

    @Test
    fun faceSetupFinishes() {
        var done = false
        composeRule.setContent {
            PrivacyDisplayTheme {
                FaceSetupScreen(
                    state = FaceSetupState(collected = 7, total = 7, done = true),
                    onDone = { done = true },
                    onBack = {},
                    camera = { modifier -> Box(modifier) },
                )
            }
        }
        composeRule.onNodeWithText(context.getString(R.string.done)).performClick()
        assertEquals(true, done)
    }
}
