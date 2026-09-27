# Privacy Screen (Android)

An Android app that makes it harder for people next to you to read your phone's screen.
It draws a privacy filter on top of every app, like a software version of a privacy screen protector.

## Features

- **Darkness filter**: tints the whole screen. A dim screen is still readable when you look straight
  at it, but it washes out quickly for someone looking from the side, where the display loses
  brightness and contrast and reflections take over.
- **Privacy pattern**: a fine pattern of lines, a grid or dots, drawn at the display's pixel size,
  similar to the slats in a physical privacy film. It breaks up the image for someone looking from an
  angle or from further away.
- **Reading spotlight**: blacks out the whole screen except a horizontal reading band. Drag the
  handle on the right edge of the screen to move the band.
- **Quick Settings tile**: toggle the filter from the notification shade.
- **Persistent notification** with a *Turn off* action while the filter is on.
- All settings apply live while the filter is running.

## Limitations

A normal app can't change how the display's pixels emit light, so it can't make the screen truly
directional the way a hardware privacy protector (micro-louver film) or a built-in privacy display
can. This app lowers how readable the screen is from the side and from a distance. It works best with:

- low system brightness,
- dark mode in your apps,
- the reading spotlight when people are close by.

For the strongest protection, use it together with a hardware privacy screen protector.

Android 12 and newer only let touches pass through an overlay that is at most 80% opaque, so the
filter never covers more than 80% of the screen. This keeps the phone usable while the filter is on.

## How it works

| Component | Role |
|---|---|
| `PrivacyOverlayService` | Foreground service that adds a full-screen, non-touchable `TYPE_APPLICATION_OVERLAY` window, plus a small draggable handle in spotlight mode. |
| `PrivacyOverlayView` | Draws the tint, the tiled pattern (`BitmapShader`) and the spotlight band. |
| `PrivacySettings` | Settings stored in `SharedPreferences`. The service listens for changes and redraws. |
| `PrivacyTileService` | Quick Settings tile. |
| `MainActivity` | Settings screen and overlay-permission flow. |

Permissions: `SYSTEM_ALERT_WINDOW` (display over other apps), `FOREGROUND_SERVICE` /
`FOREGROUND_SERVICE_SPECIAL_USE` (keep the filter running) and `POST_NOTIFICATIONS`.

## Building

Requirements: JDK 17 and the Android SDK (API 35). Android Studio already includes both.

```bash
./gradlew assembleDebug
# APK: app/build/outputs/apk/debug/app-debug.apk
adb install app/build/outputs/apk/debug/app-debug.apk
```

Each push also builds the debug APK on GitHub Actions (`.github/workflows/android.yml`).
The latest APK is published as a release: https://github.com/Innovatyou/Privacy-App/releases/latest/download/PrivacyScreen.apk

Minimum Android version: 8.0 (API 26).
