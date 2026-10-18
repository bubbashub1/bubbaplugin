# Bubba Hub Android prototype

This is an isolated Capacitor starter, not a production-ready mobile app. The links currently open staging pages; authenticated native flows, in-app navigation, app icons, permissions, offline support, and app-store compliance still need implementation.

## Local build

Requires Node.js, Android Studio, and Android SDK.

```sh
cd mobile-app
npm install
npx cap add android
npx cap sync android
npx cap open android
```

Do not commit generated signing keys or credentials. Test login, cookie persistence, payments, external booking links, and map geolocation before distribution. No live deployment is configured.
