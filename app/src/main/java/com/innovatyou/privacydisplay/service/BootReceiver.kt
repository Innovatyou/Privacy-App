package com.innovatyou.privacydisplay.service

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import com.innovatyou.privacydisplay.di.ApplicationScope
import dagger.hilt.EntryPoint
import dagger.hilt.InstallIn
import dagger.hilt.android.EntryPointAccessors
import dagger.hilt.components.SingletonComponent
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.launch

/**
 * Restarts the service after a reboot if Privacy Mode or auto-enable is on. Face detection stays
 * paused until the app is opened, because Android does not allow camera use from boot.
 */
class BootReceiver : BroadcastReceiver() {

    @EntryPoint
    @InstallIn(SingletonComponent::class)
    interface BootEntryPoint {
        fun controller(): PrivacyController

        @ApplicationScope
        fun scope(): CoroutineScope
    }

    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != Intent.ACTION_BOOT_COMPLETED) return
        val entryPoint = EntryPointAccessors.fromApplication(context, BootEntryPoint::class.java)
        val pending = goAsync()
        entryPoint.scope().launch {
            try {
                entryPoint.controller().syncService()
            } finally {
                pending.finish()
            }
        }
    }
}
