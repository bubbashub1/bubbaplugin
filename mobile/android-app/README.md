# Bubba Hub Android prototype

This Capacitor 7 Android **debug prototype** loads the live HTTPS Bubba Hub website in an Android WebView. It is not an offline app or a production-ready Play Store release.

## Build
Run the **Bubba Hub Android APK** workflow manually from GitHub Actions on this branch. Download the `bubba-hub-android-debug` artifact, unzip, and install the APK on a test Android phone.

## Before release
Test login, session persistence, back navigation, external payments, file uploads, maps, location permissions, camera/QR flows, cookies and links. Set proper app icons, splash screen, deep links and privacy disclosures. Validate Google's Play policies for remote-webview apps. Add signed release builds only after testing. Do not merge into main solely to test.
