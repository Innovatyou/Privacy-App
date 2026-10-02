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
- **Mask styles**: black mask, darkened edges, narrow viewing window, gradient mask, frosted edges
  (grainy frosted-glass fog) and custom opacity. Settings for privacy strength (0–100%), clear-area width and height, edge opacity and
  edge softness. A live preview on the main screen shows the result.
- **Orientation aware**: in landscape the clear area keeps the same physical shape, and the overlay
  resizes on rotation, folding and resolution changes.
- **Face detection (optional)**: the front camera and ML Kit run on the device to estimate whether
  someone is looking at the screen. If no face is seen, or you look away, protection goes up.
- **Multiple-viewer protection (optional)**: when a second face is seen, the mask goes to full
  strength, optionally switches to the narrow window, and a notification says
  *"Privacy Mode: Additional viewer detected."*
- **Darken when no one is looking** (switch): with face detection on, the mask gets darker while
  the camera sees no face or you look away. **Pause automatic effects in the dark** (on by
  default): when it is too dim for the camera, "no face" is not treated as "nobody looking", so the
  screen is not darkened or blurred in a dark room. Any automatic blur also shows a **Clear screen**
  button.
- **Viewer shield (blur)**: while face detection sees someone else looking (to your left, right,
  above or below), the whole screen is blurred with Android 12+ system blur, and the alert says
  which side they are on. It can also blur the screen while nobody is looking. Blur strength is
  adjustable (the shield previews live while you drag the slider, with a frosted veil that scales
  with the strength), and *Test viewer shield* previews it for 5 seconds. On phones without system blur,
  the screen is fully darkened instead.
- **Owner protection (optional)**: set up your face once in the app. While Privacy Mode is on,
  the front camera checks whether the person using the phone is you. If someone else is using it,
  the screen goes black and taps are blocked until you tap **Unlock** and confirm with your
  fingerprint or PIN. After confirming you can **lend the phone** for 5, 10, 15, 30 or 60 minutes.
  Turning Privacy Mode off (app, tile or notification), turning face detection or owner
  protection off, setting up a new face and deleting the face data all need your fingerprint or
  PIN while owner protection is on.
- **Share screen**: when you want someone to look with you, tap **Share screen** (a button that
  appears over the blur when a second face is detected, the alert notification, or the main
  screen). Privacy, blur and face detection turn off for 5, 10 or 30 minutes, and **Resume
  privacy** brings them back early.
- **Apps to ignore**: Privacy Mode turns off by itself while chosen apps are open, and back on
  when you leave them. Open it from the main screen. Google Play Store and the Android package
  installer are ignored by default, because their Install and Update buttons refuse taps while
  another app draws over the screen (an Android tap-jacking protection). Needs *Usage access*.
- **Auto-enable on screen unlock**,
  **battery-saving mode** and a **dark mode** setting.
- **Accessibility**: labelled controls, state descriptions for TalkBack, headings, touch targets of
  48 dp or more, and colour contrast of WCAG AA or better. The mask itself is hidden from
  accessibility services, so TalkBack reads the app underneath.

## What Android does not allow

These limits apply to every third-party app. The app handles them as follows:

1. **Changing the physical viewing angle.** No public API exists. Built-in privacy displays are
   controlled by the system and the phone maker. For the same reason, an app **cannot make the
   screen look blurry only to people at the side, top or bottom**: every pixel, including the
   overlay, looks the same from every angle. The viewer shield uses the camera instead. It detects
   other people and blurs the screen for everyone while they look.
2. **Blurring only part of the screen.** Android 12+ lets an overlay blur what is behind it
   (`FLAG_BLUR_BEHIND` / `blurBehindRadius`), but always the whole screen. Blurring only the edges
   of other apps would need screen capture (MediaProjection), which this app deliberately does not
   use. The system also turns blur off on some devices and in Battery Saver
   (`WindowManager.isCrossWindowBlurEnabled()`); the shield then darkens instead.
3. **A 100% black mask that still lets you use the phone.** On Android 12+, touches are blocked
   under an overlay from another app that is more than **80%** opaque
   (`InputManager.getMaximumObscuringOpacityForTouch()`). The mask window is capped at that limit.
   The app tells you about it and the preview reflects it.
4. **Covering system screens.** Overlays are not shown over the lock screen, the notification
   shade or Quick Settings, permission dialogs, or some Settings screens. The mask is also visible
   in screenshots and screen recordings.
5. **Camera from the background.** Android 11+ only lets a foreground service use the camera if it
   started while the app was visible (on Android 14+ it throws otherwise). If Privacy Mode is
   started by the tile, by an unlock or after a reboot, face detection pauses and the notification
   asks you to open the app once. The camera also pauses while another app uses it. Android 12+
   shows the green camera indicator while detection runs.
6. **Knowing which app is open.** This needs the special *Usage access* permission, granted in
   system settings. Android has no callback for it, so the app checks every 1–2 s, only while
   exclusions are set, Privacy Mode is on and the screen is on.
7. **Reacting to unlock.** Since Android 8 the unlock broadcast cannot be received from the
   manifest, so *auto-enable on unlock* keeps the service in standby, with a notification.
8. **Quick Settings tile.** Android 12+ may refuse to start a service from a tile tap. In that case
   the tile briefly opens an invisible activity to start it.
9. **Face recognition.** The app only detects faces. It does not know whether a face is yours. The
   largest face is treated as the main user.
10. **Other apps' appearance.** *Dark mode* themes this app only. Screen brightness is not changed,
   because that would need the `WRITE_SETTINGS` permission.

## Permissions

| Permission | Why | Required? |
|---|---|---|
| `SYSTEM_ALERT_WINDOW` (Display over other apps) | Draw the mask above other apps | Yes |
| `FOREGROUND_SERVICE`, `FOREGROUND_SERVICE_SPECIAL_USE` | Keep the mask running while you use other apps | Yes |
| `POST_NOTIFICATIONS` | "Privacy Mode is active" notification, *Turn off* button, viewer alerts | Recommended |
| `CAMERA`, `FOREGROUND_SERVICE_CAMERA` | Optional face detection | Only for face detection |
| `PACKAGE_USAGE_STATS` (Usage access) | *Apps to ignore*: know which app is open | Only for apps to ignore |
| `USE_BIOMETRIC` (added by AndroidX Biometric) | Fingerprint/PIN confirmation for owner protection | Install-time only |
| `RECEIVE_BOOT_COMPLETED` | Restore standby after a reboot when auto-enable is on | Install-time only |

The app has no internet permission.

## Owner protection: how it works and its limits

- Recognition uses **SFace** (a MobileFaceNet model, Apache-2.0, from the OpenCV model zoo) running
  on the device with ONNX Runtime. ML Kit finds the face and its landmarks, the face is aligned to
  112x112, and the model turns it into a 128-number face print that is compared with yours (cosine
  similarity of at least 0.40). A stranger must be seen in two checks in a row before the phone is
  blocked; seeing you unblocks it.
- **Setup** records 7 samples: 3 looking straight and 2 turned slightly to each side.
- **Your face print** is a list of numbers, not a photo. It is encrypted with AES-GCM using a key in
  the Android Keystore, stored in a no-backup folder, never uploaded, and deleted by
  *Delete my face data*.
- **Unlocking** uses Android's own BiometricPrompt (fingerprint, a biometric face unlock, or the
  screen-lock PIN, pattern or password), so a screen lock is required. The app never sees your
  fingerprint or PIN. After you unlock, other faces are ignored until the screen turns off (at
  most 30 minutes).

**Low light and photos.**

- **Darkness check:** the app measures the brightness of each camera frame and of the aligned face.
  When the face is too dark (average brightness below 50 of 255), it makes no decision instead of
  guessing, so you are not blocked by mistake. The main screen shows "Too dark to check your face".
- **Brighter exposure:** in dim rooms the camera's exposure compensation is raised to the maximum,
  and set back to normal when it is bright again.
- **Block when too dark** (optional, off by default): if it stays too dark to confirm you for about
  three checks, the phone is blocked until you unlock it.
- **Low-light screen glow** (optional): a soft white glow around the screen edges lights your face,
  like a selfie front-flash.
- **Add more face samples:** add samples taken in other lighting (for example your bedroom at
  night) without redoing the setup. Up to 20 samples are kept.
- **Blink check:** when the phone is blocked, your face only unblocks it after you blink (ML Kit
  eye-open probabilities: open, closed, open). A printed photo cannot blink. A video still can, so
  the fingerprint/PIN is the secure way to unlock.
- **Live status** on the main screen: owner recognised, checking, too dark, or someone else.

In complete darkness no app can recognise a face with a normal camera; that needs infrared
hardware (as used by some phones' own face unlock), which Android does not let apps use.

**What it cannot do.** This is a deterrent, not a replacement for your lock screen:

1. Android does not give apps access to the phone's own Face Unlock data, so the app keeps its own
   face print.
2. It uses a normal 2D camera image with no depth sensing or liveness check, so a good photo or
   video of you can fool it. It can also fail to recognise you in the dark, with sunglasses or a
   mask, or at steep angles; you then unlock with your fingerprint or PIN.
3. The block screen cannot cover the notification shade, Quick Settings, Android Settings or the
   lock screen. Someone can still force-stop the app, revoke "Display over other apps" or restart
   the phone. No normal app can prevent this.
4. It needs the camera whenever the screen is on (also in ignored apps and in Battery Saver), which
   uses more battery.
5. If you publish the app, Google Play requires you to declare the face data, and some places
   (for example the EU and Illinois) regulate face data.

For lending the phone safely, Android also has **App pinning**, **Guest users** and, on Android 15,
**Private space**.

## Privacy and security

- Camera frames are analysed in memory, one at a time, and closed right away. No photos or videos
  are saved, and nothing is uploaded.
- Without owner protection, only face *detection* runs (a count and head angle). With owner
  protection on, the app also recognises whether the main face is yours, using an encrypted face
  print stored only on this phone (see above). No photos or videos are ever saved.
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

## Third-party licences

- SFace face recognition model: Apache License 2.0
  (`app/src/main/assets/licenses/SFACE_LICENSE.txt`, see `SFACE_NOTICE.txt` for the modification).
- ONNX Runtime: MIT License. ML Kit, CameraX, Jetpack and Hilt: their respective licences.
