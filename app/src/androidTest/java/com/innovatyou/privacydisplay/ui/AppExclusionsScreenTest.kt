package com.innovatyou.privacydisplay.ui

import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.assertIsOn
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.test.ext.junit.runners.AndroidJUnit4
import androidx.test.platform.app.InstrumentationRegistry
import com.innovatyou.privacydisplay.R
import com.innovatyou.privacydisplay.data.InstalledApp
import com.innovatyou.privacydisplay.data.RecommendedExclusions
import com.innovatyou.privacydisplay.ui.theme.PrivacyDisplayTheme
import org.junit.Assert.assertEquals
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith

@RunWith(AndroidJUnit4::class)
class AppExclusionsScreenTest {

    @get:Rule
    val composeRule = createComposeRule()

    private val context = InstrumentationRegistry.getInstrumentation().targetContext
    private val playStore = InstalledApp(RecommendedExclusions.PLAY_STORE, "Google Play Store")
    private val maps = InstalledApp("com.example.maps", "Maps")

    @Test
    fun recommendedAppsAreShownAndCanBeToggled() {
        var toggled: String? = null
        composeRule.setContent {
            PrivacyDisplayTheme {
                AppExclusionsScreen(
                    apps = listOf(playStore, maps),
                    recommended = listOf(playStore),
                    excluded = setOf(playStore.packageName),
                    usageAccessGranted = true,
                    onToggle = { toggled = it },
                    onGrantUsageAccess = {},
                    onBack = {},
                )
            }
        }
        composeRule.onNodeWithText(context.getString(R.string.exclusions_recommended)).assertIsDisplayed()
        composeRule.onNodeWithTag(appRowTag(playStore.packageName)).assertIsOn()
        composeRule.onNodeWithTag(appRowTag(maps.packageName)).performClick()
        assertEquals(maps.packageName, toggled)
    }

    @Test
    fun missingUsageAccessIsExplained() {
        var asked = false
        composeRule.setContent {
            PrivacyDisplayTheme {
                AppExclusionsScreen(
                    apps = listOf(maps),
                    recommended = emptyList(),
                    excluded = emptySet(),
                    usageAccessGranted = false,
                    onToggle = {},
                    onGrantUsageAccess = { asked = true },
                    onBack = {},
                )
            }
        }
        composeRule.onNodeWithText(context.getString(R.string.usage_access_rationale)).assertIsDisplayed()
        composeRule.onNodeWithText(context.getString(R.string.perm_grant)).performClick()
        assertEquals(true, asked)
    }
}
