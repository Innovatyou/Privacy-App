package com.innovatyou.privacydisplay.viewmodel

import com.innovatyou.privacydisplay.data.InstalledApp
import com.innovatyou.privacydisplay.data.InstalledAppsRepository
import com.innovatyou.privacydisplay.data.MaskMode
import com.innovatyou.privacydisplay.data.PreferencesRepository
import com.innovatyou.privacydisplay.data.PrivacySettings
import com.innovatyou.privacydisplay.data.RecommendedExclusions
import com.innovatyou.privacydisplay.owner.OwnerFaceStore
import com.innovatyou.privacydisplay.service.PrivacyController
import com.innovatyou.privacydisplay.service.PrivacyRuntime
import com.innovatyou.privacydisplay.util.PermissionManager
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class PrivacyViewModelTest {

    private val dispatcher = StandardTestDispatcher()
    private val repository = FakeRepository()
    private val controller = FakeController(repository)
    private val permissions = FakePermissions()
    private val ownerFaces = FakeOwnerFaceStore()
    private lateinit var viewModel: PrivacyViewModel

    @Before
    fun setUp() {
        Dispatchers.setMain(dispatcher)
        viewModel = PrivacyViewModel(repository, controller, permissions, FakeApps(), PrivacyRuntime(), ownerFaces)
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    @Test
    fun `turning privacy on without the overlay permission asks for it`() = runTest(dispatcher) {
        permissions.overlay = false
        viewModel.setPrivacyEnabled(true)
        advanceUntilIdle()
        assertEquals(PrivacyEvent.RequestOverlayPermission, viewModel.events.first())
        assertTrue(controller.calls.isEmpty())
        assertFalse(repository.state.value.enabled)
    }

    @Test
    fun `turning privacy on with permission starts it`() = runTest(dispatcher) {
        viewModel.setPrivacyEnabled(true)
        advanceUntilIdle()
        assertEquals(listOf(true), controller.calls)
        assertTrue(repository.state.value.enabled)
    }

    @Test
    fun `slider changes are saved`() = runTest(dispatcher) {
        viewModel.setStrength(0.2f)
        viewModel.setClearAreaHeight(0.6f)
        viewModel.setEdgeOpacity(0.3f)
        viewModel.setMaskMode(MaskMode.GRADIENT)
        advanceUntilIdle()
        val s = repository.state.value
        assertEquals(0.2f, s.strength, 0f)
        assertEquals(0.6f, s.clearAreaHeight, 0f)
        assertEquals(0.3f, s.edgeOpacity, 0f)
        assertEquals(MaskMode.GRADIENT, s.maskMode)
    }

    @Test
    fun `face detection needs the camera permission`() = runTest(dispatcher) {
        permissions.camera = false
        viewModel.setFaceDetection(true)
        advanceUntilIdle()
        assertEquals(PrivacyEvent.RequestCameraPermission, viewModel.events.first())
        assertFalse(repository.state.value.faceDetectionEnabled)

        viewModel.onCameraPermissionResult(granted = true)
        advanceUntilIdle()
        assertTrue(repository.state.value.faceDetectionEnabled)
    }

    @Test
    fun `turning face detection off also turns off multiple viewer protection`() = runTest(dispatcher) {
        repository.state.value = PrivacySettings(faceDetectionEnabled = true, multipleViewerProtection = true)
        viewModel.setFaceDetection(false)
        advanceUntilIdle()
        assertFalse(repository.state.value.faceDetectionEnabled)
        assertFalse(repository.state.value.multipleViewerProtection)
    }

    @Test
    fun `multiple viewer protection requires face detection`() = runTest(dispatcher) {
        viewModel.setMultipleViewerProtection(true)
        advanceUntilIdle()
        assertFalse(repository.state.value.multipleViewerProtection)
    }

    @Test
    fun `excluded apps can be added and removed`() = runTest(dispatcher) {
        viewModel.toggleExcludedApp("com.example.bank")
        advanceUntilIdle()
        assertEquals(RecommendedExclusions.PACKAGES + "com.example.bank", repository.state.value.excludedApps)
        viewModel.toggleExcludedApp("com.example.bank")
        advanceUntilIdle()
        assertEquals(RecommendedExclusions.PACKAGES, repository.state.value.excludedApps)
    }

    @Test
    fun `play store is ignored by default and can be un-ignored`() = runTest(dispatcher) {
        assertTrue(RecommendedExclusions.PLAY_STORE in repository.state.value.excludedApps)
        viewModel.toggleExcludedApp(RecommendedExclusions.PLAY_STORE)
        advanceUntilIdle()
        assertFalse(RecommendedExclusions.PLAY_STORE in repository.state.value.excludedApps)
    }

    @Test
    fun `auto enable on unlock starts the service in standby`() = runTest(dispatcher) {
        viewModel.setAutoEnableOnUnlock(true)
        advanceUntilIdle()
        assertTrue(repository.state.value.autoEnableOnUnlock)
        assertEquals(1, controller.syncs)
    }

    @Test
    fun `viewer shield settings are saved`() = runTest(dispatcher) {
        viewModel.setBlurOnExtraViewer(false)
        viewModel.setBlurWhenAway(true)
        viewModel.setBlurStrength(0.9f)
        advanceUntilIdle()
        val s = repository.state.value
        assertFalse(s.blurOnExtraViewer)
        assertTrue(s.blurWhenAway)
        assertEquals(0.9f, s.blurStrength, 0f)
    }

    @Test
    fun `dragging the blur slider previews the shield while privacy is on`() = runTest(dispatcher) {
        viewModel.setBlurStrength(0.5f)
        advanceUntilIdle()
        assertEquals(0, controller.shieldTests) // Privacy Mode is off: nothing to preview on.

        repository.state.value = repository.state.value.copy(enabled = true)
        viewModel.setBlurStrength(0.4f)
        advanceUntilIdle()
        assertEquals(0.4f, repository.state.value.blurStrength, 0f)
        assertEquals(1, controller.shieldTests)
    }

    @Test
    fun `sharing the screen can be started and stopped`() = runTest(dispatcher) {
        viewModel.setShareMinutes(30)
        advanceUntilIdle()
        assertEquals(30, repository.state.value.shareMinutes)
        viewModel.startSharing()
        assertTrue(controller.sharing)
        viewModel.stopSharing()
        assertFalse(controller.sharing)
    }

    @Test
    fun `invalid share durations fall back to the default`() = runTest(dispatcher) {
        viewModel.setShareMinutes(7)
        advanceUntilIdle()
        assertEquals(PrivacySettings.DEFAULT_SHARE_MINUTES, repository.state.value.shareMinutes)
    }

    @Test
    fun `testing the shield goes through the controller`() = runTest(dispatcher) {
        viewModel.testShield()
        assertEquals(1, controller.shieldTests)
    }

    @Test
    fun `owner protection needs a face set up first`() = runTest(dispatcher) {
        viewModel.setOwnerProtection(true)
        advanceUntilIdle()
        assertEquals(PrivacyEvent.OpenFaceSetup, viewModel.events.first())
        assertFalse(repository.state.value.ownerProtection)
    }

    @Test
    fun `owner protection needs a screen lock`() = runTest(dispatcher) {
        permissions.screenLock = false
        viewModel.setOwnerProtection(true)
        advanceUntilIdle()
        assertEquals(PrivacyEvent.ScreenLockNeeded, viewModel.events.first())
    }

    @Test
    fun `finishing face setup turns protection on`() = runTest(dispatcher) {
        ownerFaces.save(listOf(FloatArray(128)))
        viewModel.onFaceSetUp()
        advanceUntilIdle()
        assertTrue(repository.state.value.ownerProtection)
        assertTrue(repository.state.value.faceDetectionEnabled)
    }

    @Test
    fun `with owner protection turning privacy off asks for the owner`() = runTest(dispatcher) {
        ownerFaces.save(listOf(FloatArray(128)))
        repository.state.value = PrivacySettings(enabled = true, faceDetectionEnabled = true, ownerProtection = true)
        advanceUntilIdle()

        viewModel.setPrivacyEnabled(false)
        advanceUntilIdle()
        assertEquals(PrivacyEvent.Authenticate(GatedAction.DISABLE_PRIVACY), viewModel.events.first())
        assertTrue(controller.calls.isEmpty())

        viewModel.onAuthenticated(GatedAction.DISABLE_PRIVACY)
        advanceUntilIdle()
        assertEquals(listOf(false), controller.calls)
        assertEquals(1, controller.ownerConfirmations)
    }

    @Test
    fun `with owner protection lending and deleting the face need the owner`() = runTest(dispatcher) {
        ownerFaces.save(listOf(FloatArray(128)))
        repository.state.value = PrivacySettings(enabled = true, faceDetectionEnabled = true, ownerProtection = true)
        advanceUntilIdle()

        viewModel.startSharing()
        advanceUntilIdle()
        assertEquals(PrivacyEvent.Authenticate(GatedAction.START_SHARING), viewModel.events.first())
        assertFalse(controller.sharing)

        viewModel.deleteFace()
        advanceUntilIdle()
        assertEquals(PrivacyEvent.Authenticate(GatedAction.DELETE_FACE), viewModel.events.first())
        assertTrue(ownerFaces.enrolled.value)

        viewModel.onAuthenticated(GatedAction.DELETE_FACE)
        advanceUntilIdle()
        assertFalse(ownerFaces.enrolled.value)
        assertFalse(repository.state.value.ownerProtection)
    }

    @Test
    fun `installed apps are loaded once`() = runTest(dispatcher) {
        viewModel.loadApps()
        advanceUntilIdle()
        assertEquals(listOf("Maps"), viewModel.apps.value?.map { it.label })
        assertEquals(listOf("Google Play Store"), viewModel.recommendedApps.value.map { it.label })
    }
}

private class FakeRepository : PreferencesRepository {
    val state = MutableStateFlow(PrivacySettings())
    override val settings = state
    override suspend fun update(transform: (PrivacySettings) -> PrivacySettings) {
        state.update { transform(it).sanitized() }
    }
}

private class FakeController(private val repository: FakeRepository) : PrivacyController {
    val calls = mutableListOf<Boolean>()
    var syncs = 0
    override suspend fun setPrivacyEnabled(enabled: Boolean): Boolean {
        calls += enabled
        repository.update { it.copy(enabled = enabled) }
        return true
    }
    override suspend fun syncService(): Boolean {
        syncs++
        return true
    }
    override fun onAppForeground() = Unit
    var shieldTests = 0
    override fun testShield() {
        shieldTests++
    }
    var sharing = false
    override fun startSharing(minutes: Int?) {
        sharing = true
    }
    var ownerConfirmations = 0
    override fun ownerAuthenticated() {
        ownerConfirmations++
    }
    override fun stopSharing() {
        sharing = false
    }
}

private class FakePermissions : PermissionManager {
    var overlay = true
    var camera = true
    override fun canDrawOverlays() = overlay
    override fun hasCameraPermission() = camera
    override fun hasNotificationPermission() = true
    override fun hasUsageAccess() = false
    override fun hasFrontCamera() = true
    override fun supportsWindowBlur() = true
    var screenLock = true
    override fun hasScreenLock() = screenLock
    override fun maxOverlayOpacity() = 0.8f
}

private class FakeOwnerFaceStore : OwnerFaceStore {
    override val enrolled = MutableStateFlow(false)
    var saved: List<FloatArray> = emptyList()
    override suspend fun save(embeddings: List<FloatArray>) {
        saved = embeddings
        enrolled.value = true
    }
    override suspend fun load() = saved
    override suspend fun delete() {
        saved = emptyList()
        enrolled.value = false
    }
}

private class FakeApps : InstalledAppsRepository {
    override suspend fun launchableApps() = listOf(InstalledApp("com.example.maps", "Maps"))
    override suspend fun recommendedApps() = listOf(InstalledApp(RecommendedExclusions.PLAY_STORE, "Google Play Store"))
}
