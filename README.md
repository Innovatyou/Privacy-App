# Privacy Display (Android)

A software privacy screen for Android phones. It draws a privacy mask over every app, keeping the
centre of the screen readable for you while making it harder for people beside you to read it. It
can optionally use the front camera to raise protection when someone else is looking.

**Download the latest build:**
https://github.com/Innovatyou/Privacy-App/releases/download/latest/PrivacyDisplay.apk

## Hardware vs. software privacy

| | Hardware privacy display | Software privacy (this app) |
|---|---|---|
| How it works | Special display layers (micro-louvres or a switchable backlight) physically narrow the viewing angle | Overlays, masking, optional face detection |
| Needs | Display hardware support, controlled by the phone maker | Any Android 8.0+ phone |
| Can it change the viewing angle? | Yes | **No.** It cannot change how an OLED/LCD panel emits light |

> Software privacy protection cannot physically change your display's viewing angle. For maximum
> privacy, combine this feature with a physical privacy screen protector.

## Features

- **Privacy Mode**: a large on/off toggle, plus a Quick Settings tile (*Privacy Display: On/Off*)
  and a persistent notification with a *Turn off* button.
- **Mask styles**: black mask, darkened edges, narrow viewing window, gradient mask and custom
  opacity. Settings for privacy strength (0–100%), clear-area width and height, edge opacity and
  edge softness. A live preview on the main screen shows the result.
- **Orientation aware**: in landscape the clear area keeps the same physical shape, and the overlay
  resizes on rotation, folding and resolution changes.
- **Face detection (optional)**: the front camera and ML Kit run on the device to estimate whether
  someone is looking at the screen. If no face is seen, or you look away, protection goes up.
- **Multiple-viewer protection (optional)**: when a second face is seen, the mask goes to full
  strength, optionally switches to the narrow window, and a notification says
  *"Privacy Mode: Additional viewer detected."*
- **Auto-enable on screen unlock**, **app exclusions** (the mask pauses in apps you choose),
  **battery-saving mode** and a **dark mode** setting.
- **Accessibility**: labelled controls, state descriptions for TalkBack, headings, touch targets of
  48 dp or more, and colour contrast of WCAG AA or better. The mask itself is hidden from
  accessibility services, so TalkBack reads the app underneath.

## What Android does not allow

These limits apply to every third-party app. The app handles them as follows:

1. **Changing the physical viewing angle.** No public API exists. Built-in privacy displays are
   controlled by the system and the phone maker.
2. **A 100% black mask that still lets you use the phone.** On Android 12+, touches are blocked
   under an overlay from another app that is more than **80%** opaque
   (`InputManager.getMaximumObscuringOpacityForTouch()`). The mask window is capped at that limit.
   The app tells you about it and the preview reflects it.
3. **Covering system screens.** Overlays are not shown over the lock screen, the notification
   shade or Quick Settings, permission dialogs, or some Settings screens. The mask is also visible
   in screenshots and screen recordings.
4. **Camera from the background.** Android 11+ only lets a foreground service use the camera if it
   started while the app was visible (on Android 14+ it throws otherwise). If Privacy Mode is
   started by the tile, by an unlock or after a reboot, face detection pauses and the notification
   asks you to open the app once. The camera also pauses while another app uses it. Android 12+
   shows the green camera indicator while detection runs.
5. **Knowing which app is open.** This needs the special *Usage access* permission, granted in
   system settings. Android has no callback for it, so the app checks every 1–2 s, only while
   exclusions are set, Privacy Mode is on and the screen is on.
6. **Reacting to unlock.** Since Android 8 the unlock broadcast cannot be received from the
   manifest, so *auto-enable on unlock* keeps the service in standby, with a notification.
7. **Quick Settings tile.** Android 12+ may refuse to start a service from a tile tap. In that case
   the tile briefly opens an invisible activity to start it.
8. **Face recognition.** The app only detects faces. It does not know whether a face is yours. The
   largest face is treated as the main user.
9. **Other apps' appearance.** *Dark mode* themes this app only. Screen brightness is not changed,
   because that would need the `WRITE_SETTINGS` permission.

## Permissions

| Permission | Why | Required? |
|---|---|---|
| `SYSTEM_ALERT_WINDOW` (Display over other apps) | Draw the mask above other apps | Yes |
| `FOREGROUND_SERVICE`, `FOREGROUND_SERVICE_SPECIAL_USE` | Keep the mask running while you use other apps | Yes |
| `POST_NOTIFICATIONS` | "Privacy Mode is active" notification, *Turn off* button, viewer alerts | Recommended |
| `CAMERA`, `FOREGROUND_SERVICE_CAMERA` | Optional face detection | Only for face detection |
| `PACKAGE_USAGE_STATS` (Usage access) | Optional app exclusions | Only for exclusions |
| `RECEIVE_BOOT_COMPLETED` | Restore standby after a reboot when auto-enable is on | Install-time only |

The app has no internet permission.

## Privacy and security

- Camera frames are analysed in memory, one at a time, and closed right away. No photos or videos
  are saved, and nothing is uploaded.
- Only face *detection* runs (a count and head angle). The app does no facial recognition, identity
  matching or biometric storage.
- The app never reads contacts, messages, passwords or screen contents.
- Only the app's own settings are stored, on the device (DataStore). Backup is turned off.

## Battery

- With face detection off, the camera is never used. Only the overlay runs.
- With face detection on, the camera runs only while the mask is showing and the screen is on. It
  uses a low-resolution analysis stream (no preview), keeps only the latest frame, and analyses at
  most one frame every 0.5 s (every 1.5 s in battery-saving mode) on a background thread.
- In battery-saving mode, detection pauses while the system Battery Saver is on.
- Soft masks are rendered once into a small bitmap and only redrawn when a setting changes.

## Architecture

Kotlin · Jetpack Compose · Material 3 · MVVM · StateFlow · Coroutines · Hilt · DataStore ·
CameraX · ML Kit Face Detection · foreground service

```
com.innovatyou.privacydisplay
├── data        PrivacySettings, PreferencesRepository (DataStore), InstalledAppsRepository
├── di          Hilt modules
├── service     PrivacyOverlayService, PrivacyOverlayWindow, PrivacyNotification,
│               PrivacyController, PrivacyRuntime, PrivacyTileService, BootReceiver
├── camera      FaceDetectionManager (CameraX + ML Kit), CameraAnalyzer, ViewerStateSmoother
├── overlay     PrivacyPolicy, MaskSpec (pure geometry), MaskRenderer, PrivacyMaskView
├── ui          MainActivity, PrivacyDisplayScreen, SettingsScreen, AppExclusionsScreen,
│               ToggleActivity, components, theme
├── viewmodel   PrivacyViewModel
└── util        PermissionManager, OrientationManager, DeviceStateMonitor, ForegroundAppMonitor
```

The overlay window uses `TYPE_APPLICATION_OVERLAY` with `FLAG_NOT_TOUCHABLE | FLAG_NOT_FOCUSABLE |
FLAG_LAYOUT_IN_SCREEN | FLAG_LAYOUT_NO_LIMITS`. It covers the display cutout and ignores insets, has
its alpha capped at the touch-obscuring limit, and on Android 11+ is added through
`Context.createWindowContext()`. It is sized from `WindowManager.getMaximumWindowMetrics()` and
removed as soon as Privacy Mode turns off or an excluded app is open.

## Building

Requirements: Android Studio (Ladybug or newer) or JDK 17 with the Android SDK (API 35).

```bash
./gradlew assembleDebug            # app/build/outputs/apk/debug/app-debug.apk
./gradlew testDebugUnitTest        # unit tests
./gradlew connectedDebugAndroidTest   # Compose UI tests (needs a device or emulator)
```

Toolchain: Android Gradle Plugin 8.7.3, Kotlin 2.0.21, Gradle 8.11.1, compile/target SDK 35,
min SDK 26. Versions are in `gradle/libs.versions.toml`.

Debug builds are signed with the shared `app/debug.keystore`, so each new build installs over the
previous one. For a signed release build, set `RELEASE_KEYSTORE`, `RELEASE_KEYSTORE_PASSWORD`,
`RELEASE_KEY_ALIAS` and `RELEASE_KEY_PASSWORD`, then run `./gradlew assembleRelease`.

Every push is built by GitHub Actions (`.github/workflows/android.yml`). The workflow builds debug
and minified release APKs, runs the unit tests, runs the UI tests on an emulator, and publishes the
debug APK to the `latest` release.

## Installing

1. Download `PrivacyDisplay.apk` from the link at the top, in your phone's browser.
2. Open it and allow installs from your browser when Android asks. If Play Protect warns about an
   unknown app, choose *Install anyway*.
3. Open **Privacy Display**, tap **Allow** and turn on *Display over other apps*, then tap **ON**.
4. Optional: add the **Privacy Display** tile to Quick Settings.

If you installed the earlier "Privacy Screen" test app, uninstall it. This app replaces it.
