package com.innovatyou.privacydisplay.di

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.PreferenceDataStoreFactory
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.preferencesDataStoreFile
import com.innovatyou.privacydisplay.data.DataStorePreferencesRepository
import com.innovatyou.privacydisplay.data.InstalledAppsRepository
import com.innovatyou.privacydisplay.data.PackageManagerAppsRepository
import com.innovatyou.privacydisplay.data.PreferencesRepository
import com.innovatyou.privacydisplay.service.PrivacyController
import com.innovatyou.privacydisplay.service.ServicePrivacyController
import com.innovatyou.privacydisplay.util.AndroidPermissionManager
import com.innovatyou.privacydisplay.util.PermissionManager
import dagger.Binds
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import javax.inject.Qualifier
import javax.inject.Singleton
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob

/** Scope for work that must outlive a screen, such as starting the service from a receiver. */
@Qualifier
@Retention(AnnotationRetention.BINARY)
annotation class ApplicationScope

@Module
@InstallIn(SingletonComponent::class)
object AppModule {

    @Provides
    @Singleton
    @ApplicationScope
    fun provideApplicationScope(): CoroutineScope = CoroutineScope(SupervisorJob() + Dispatchers.Default)

    @Provides
    @Singleton
    fun provideDataStore(
        @ApplicationContext context: Context,
        @ApplicationScope scope: CoroutineScope,
    ): DataStore<Preferences> = PreferenceDataStoreFactory.create(
        scope = CoroutineScope(scope.coroutineContext + Dispatchers.IO),
        produceFile = { context.preferencesDataStoreFile("privacy_settings") },
    )
}

@Module
@InstallIn(SingletonComponent::class)
abstract class BindingsModule {
    @Binds
    abstract fun bindPreferencesRepository(impl: DataStorePreferencesRepository): PreferencesRepository

    @Binds
    abstract fun bindPrivacyController(impl: ServicePrivacyController): PrivacyController

    @Binds
    abstract fun bindPermissionManager(impl: AndroidPermissionManager): PermissionManager

    @Binds
    abstract fun bindInstalledAppsRepository(impl: PackageManagerAppsRepository): InstalledAppsRepository
}
