package com.innovatyou.privacydisplay.owner

import com.innovatyou.privacydisplay.data.PrivacySettings

/** Rules for owner protection: when the phone is blocked and when it is released. */
object OwnerPolicy {

    /** Owner protection runs while Privacy Mode is on, protection is enabled and a face is set up. */
    fun isActive(settings: PrivacySettings, enrolled: Boolean): Boolean =
        settings.enabled && settings.ownerProtection && enrolled

    /**
     * New lock state after a face check. Seeing the owner unlocks; a confirmed stranger locks,
     * unless the owner recently unlocked with a fingerprint or PIN ([ownerTrusted]).
     */
    fun nextLocked(locked: Boolean, decision: OwnerDecision, ownerTrusted: Boolean): Boolean = when (decision) {
        OwnerDecision.OWNER -> false
        OwnerDecision.STRANGER -> if (ownerTrusted) locked else true
        OwnerDecision.UNKNOWN -> locked
    }

    /** The block screen covers everything unless the screen is shared or the unlock screen is open. */
    fun showBlockScreen(active: Boolean, locked: Boolean, sharing: Boolean, unlockScreenOpen: Boolean): Boolean =
        active && locked && !sharing && !unlockScreenOpen
}
