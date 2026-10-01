package com.innovatyou.privacydisplay.ui

import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.semantics.SemanticsProperties
import androidx.compose.ui.test.SemanticsMatcher
import androidx.compose.ui.test.assert
import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.compose.ui.test.performScrollTo
import androidx.test.ext.junit.runners.AndroidJUnit4
import androidx.test.platform.app.InstrumentationRegistry
import com.innovatyou.privacydisplay.R
import com.innovatyou.privacydisplay.data.PrivacySettings
import com.innovatyou.privacydisplay.ui.components.PRIVACY_TOGGLE_TAG
import com.innovatyou.privacydisplay.ui.theme.PrivacyDisplayTheme
import com.innovatyou.privacydisplay.util.PermissionState
import com.innovatyou.privacydisplay.viewmodel.PrivacyUiState
import org.junit.Assert.assertEquals
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith

@RunWith(AndroidJUnit4::class)
class PrivacyDisplayScreenTest {

    @get:Rule
    val composeRule = createComposeRule()

    private val context = InstrumentationRegistry.getInstrumentation().targetContext

    private fun state(enabled: Boolean = false, overlay: Boolean = true) = PrivacyUiState(
        settings = PrivacySettings(enabled = enabled),
        permissions = PermissionState(overlay = overlay, camera = true, notifications = true, usageAccess = true),
        maxOverlayOpacity = 0.8f,
        loaded = true,
    )

    @Test
    fun toggleReportsItsStateAndCallsBack() {
        var toggledTo: Boolean? = null
        composeRule.setContent {
            PrivacyDisplayTheme {
                PrivacyDisplayScreen(
                    state = state(enabled = false),
                    actions = PrivacyActions(onPrivacyToggle = { toggledTo = it }),
                    onOpenSettings = {},
                )
            }
        }
        composeRule.onNodeWithTag(PRIVACY_TOGGLE_TAG)
            .assertIsDisplayed()
            .assert(SemanticsMatcher.expectValue(SemanticsProperties.StateDescription, context.getString(R.string.state_off)))
            .performClick()
        assertEquals(true, toggledTo)
    }

    @Test
    fun activeStatusIsShown() {
        composeRule.setContent {
            PrivacyDisplayTheme {
                PrivacyDisplayScreen(state = state(enabled = true), actions = PrivacyActions(), onOpenSettings = {})
            }
        }
        composeRule.onNodeWithText(context.getString(R.string.status_active)).assertIsDisplayed()
    }

    @Test
    fun slidersAreLabelledForAccessibility() {
        composeRule.setContent {
            PrivacyDisplayTheme {
                PrivacyDisplayScreen(state = state(), actions = PrivacyActions(), onOpenSettings = {})
            }
        }
        composeRule.onNodeWithTag(STRENGTH_SLIDER_TAG)
            .performScrollTo()
            .assert(SemanticsMatcher.expectValue(SemanticsProperties.ContentDescription, listOf(context.getString(R.string.privacy_strength))))
            .assert(SemanticsMatcher.expectValue(SemanticsProperties.StateDescription, "75%"))
        composeRule.onNodeWithTag(VIEWING_AREA_SLIDER_TAG)
            .performScrollTo()
            .assert(SemanticsMatcher.expectValue(SemanticsProperties.StateDescription, "45%"))
    }

    @Test
    fun appsToIgnoreOpenFromTheMainScreen() {
        var opened = false
        composeRule.setContent {
            PrivacyDisplayTheme {
                PrivacyDisplayScreen(
                    state = state(),
                    actions = PrivacyActions(),
                    onOpenSettings = {},
                    onOpenExclusions = { opened = true },
                )
            }
        }
        composeRule.onNodeWithTag(EXCLUSIONS_ROW_TAG).performScrollTo().performClick()
        assertEquals(true, opened)
    }

    @Test
    fun shareScreenStartsAndStopsSharing() {
        var started = false
        var stopped = false
        var sharingUntil: Long? by mutableStateOf(null)
        composeRule.setContent {
            PrivacyDisplayTheme {
                PrivacyDisplayScreen(
                    state = state(enabled = true).copy(sharingUntil = sharingUntil),
                    actions = PrivacyActions(onStartSharing = { started = true }, onStopSharing = { stopped = true }),
                    onOpenSettings = {},
                )
            }
        }
        composeRule.onNodeWithTag(START_SHARING_TAG).performScrollTo().performClick()
        assertEquals(true, started)

        sharingUntil = System.currentTimeMillis() + 60_000
        composeRule.onNodeWithTag(STOP_SHARING_TAG).performScrollTo().performClick()
        assertEquals(true, stopped)
    }

    @Test
    fun softwareLimitationIsExplained() {
        composeRule.setContent {
            PrivacyDisplayTheme {
                PrivacyDisplayScreen(state = state(), actions = PrivacyActions(), onOpenSettings = {})
            }
        }
        composeRule.onNodeWithText(context.getString(R.string.disclaimer), substring = true)
            .performScrollTo()
            .assertIsDisplayed()
    }

    @Test
    fun missingOverlayPermissionOffersToGrantIt() {
        var granted = false
        composeRule.setContent {
            PrivacyDisplayTheme {
                PrivacyDisplayScreen(
                    state = state(overlay = false),
                    actions = PrivacyActions(onGrantOverlay = { granted = true }),
                    onOpenSettings = {},
                )
            }
        }
        composeRule.onNodeWithText(context.getString(R.string.perm_grant)).performScrollTo().performClick()
        assertEquals(true, granted)
    }
}
