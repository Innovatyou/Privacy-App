package com.innovatyou.privacydisplay.data

import androidx.datastore.preferences.core.mutablePreferencesOf
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class RecommendedExclusionsTest {

    @Test
    fun `new installs ignore the play store and installer by default`() {
        assertTrue(RecommendedExclusions.PLAY_STORE in PrivacySettings().excludedApps)
        assertEquals(RecommendedExclusions.PACKAGES, PrivacySettings().excludedApps)
    }

    @Test
    fun `existing exclusions get the recommended apps added once`() = runTest {
        val migration = RecommendedExclusions.migration
        val old = mutablePreferencesOf(PreferenceKeys.EXCLUDED_APPS to setOf("com.example.bank"))
        assertTrue(migration.shouldMigrate(old))

        val migrated = migration.migrate(old)
        assertEquals(
            RecommendedExclusions.PACKAGES + "com.example.bank",
            migrated.toPrivacySettings().excludedApps,
        )
        assertFalse(migration.shouldMigrate(migrated))
    }

    @Test
    fun `apps the user removed later are not added back`() = runTest {
        val migrated = RecommendedExclusions.migration.migrate(mutablePreferencesOf())
        val edited = migrated.toMutablePreferences().apply {
            writePrivacySettings(migrated.toPrivacySettings().copy(excludedApps = emptySet()))
        }
        assertFalse(RecommendedExclusions.migration.shouldMigrate(edited))
        assertTrue(edited.toPrivacySettings().excludedApps.isEmpty())
    }
}
