package com.innovatyou.privacydisplay.ui

import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.assertIsNotEnabled
import androidx.compose.ui.test.assertIsSelected
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.compose.ui.test.performScrollTo
import androidx.test.ext.junit.runners.AndroidJUnit4
import androidx.test.platform.app.InstrumentationRegistry
import com.innovatyou.privacydisplay.R
import com.innovatyou.privacydisplay.data.MaskMode
import com.innovatyou.privacydisplay.data.PrivacySettings
import com.innovatyou.privacydisplay.ui.theme.PrivacyDisplayTheme
import com.innovatyou.privacydisplay.util.PermissionState
import com.innovatyou.privacydisplay.viewmodel.PrivacyUiState
import org.junit.Assert.assertEquals
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith

@RunWith(AndroidJUnit4::class)
class SettingsScreenTest {

    @get:Rule
    val composeRule = createComposeRule()

    private val context = InstrumentationRegistry.getInstrumentation().targetContext

    private val state = PrivacyUiState(
        settings = PrivacySettings(maskMode = MaskMode.DARK_EDGES, faceDetectionEnabled = false),
        permissions = PermissionState(overlay = true, camera = false, notifications = true, usageAccess = false),
        maxOverlayOpacity = 0.8f,
        loaded = true,
    )

    @Test
    fun maskModeCanBeSelected() {
        var selected: MaskMode? = null
        composeRule.setContent {
            PrivacyDisplayTheme {
                SettingsScreen(
                    state = state,
                    actions = PrivacyActions(onMaskModeChange = { selected = it }),
                    onBack = {},
                    onOpenExclusions = {},
                )
            }
        }
        composeRule.onNodeWithTag(maskModeTag(MaskMode.DARK_EDGES)).performScrollTo().assertIsSelected()
        composeRule.onNodeWithTag(maskModeTag(MaskMode.NARROW_WINDOW)).performScrollTo().performClick()
        assertEquals(MaskMode.NARROW_WINDOW, selected)
    }

    @Test
    fun multipleViewerProtectionNeedsFaceDetection() {
        composeRule.setContent {
            PrivacyDisplayTheme {
                SettingsScreen(state = state, actions = PrivacyActions(), onBack = {}, onOpenExclusions = {})
            }
        }
        composeRule.onNodeWithText(context.getString(R.string.protect_multiple))
            .performScrollTo()
            .assertIsNotEnabled()
    }

    @Test
    fun exclusionsOpenFromSettings() {
        var opened = false
        composeRule.setContent {
            PrivacyDisplayTheme {
                SettingsScreen(state = state, actions = PrivacyActions(), onBack = {}, onOpenExclusions = { opened = true })
            }
        }
        composeRule.onNodeWithText(context.getString(R.string.app_exclusions)).performScrollTo().performClick()
        assertEquals(true, opened)
    }

    @Test
    fun cameraPermissionIsExplained() {
        composeRule.setContent {
            PrivacyDisplayTheme {
                SettingsScreen(state = state, actions = PrivacyActions(), onBack = {}, onOpenExclusions = {})
            }
        }
        composeRule.onNodeWithText(context.getString(R.string.perm_camera_why)).performScrollTo().assertIsDisplayed()
    }
}
