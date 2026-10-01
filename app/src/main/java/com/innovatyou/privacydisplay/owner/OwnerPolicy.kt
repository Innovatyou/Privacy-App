package com.innovatyou.privacydisplay.owner

import com.innovatyou.privacydisplay.data.PrivacySettings

/** Rules for owner protection: when the phone is blocked and when it is released. */
object OwnerPolicy {

    /** Owner protection runs while Privacy Mode is on, protection is enabled and a face is set up. */
    fun isActive(settings: PrivacySettings, enrolled: Boolean): Boolean =
        settings.enabled && settings.ownerProtection && enrolled

    /**
     * New lock state after a face check.
     * - A confirmed stranger locks, unless the owner recently unlocked with a fingerprint or PIN
     *   ([ownerTrusted]).
     * - The owner's face unlocks a locked phone only after a recent blink ([blinked]), so a
     *   printed photo cannot unlock it.
     * - Too dark to check: locks only with "block when too dark" ([blockWhenTooDark]).
     */
    fun nextLocked(
        locked: Boolean,
        decision: OwnerDecision,
        ownerTrusted: Boolean,
        blockWhenTooDark: Boolean = false,
        blinked: Boolean = true,
    ): Boolean = when (decision) {
        OwnerDecision.OWNER -> locked && !blinked
        OwnerDecision.STRANGER -> if (ownerTrusted) locked else true
        OwnerDecision.TOO_DARK -> if (blockWhenTooDark && !ownerTrusted) true else locked
        OwnerDecision.UNKNOWN -> locked
    }

    /** The block screen covers everything unless the screen is shared or the unlock screen is open. */
    fun showBlockScreen(active: Boolean, locked: Boolean, sharing: Boolean, unlockScreenOpen: Boolean): Boolean =
        active && locked && !sharing && !unlockScreenOpen
}
