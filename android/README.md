# Kris Kringle for Android

Jetpack Compose version of the SwiftUI app, using the existing Kris Kringle backend.

## Build

Open this directory in Android Studio, or run with JDK 25 and Android SDK 37 installed:

```sh
./gradlew :app:assembleDebug :app:testDebugUnitTest :app:lintDebug
```

The debug APK is `app/build/outputs/apk/debug/app-debug.apk`.
Run `./gradlew :app:connectedDebugAndroidTest` with an emulator to check sign-in navigation and validation without sending an email.

## Google sign-in configuration

Google sign-in uses Android Credential Manager and exchanges the ID token with the backend's `loginGoogle` endpoint. Configuration is deferred for now; email one-time-code sign-in works independently.

Before testing Google sign-in:

1. Create an Android OAuth client in the existing Google Cloud project for `com.selmasu.kriskringle`, with the debug/release signing certificate SHA-1 fingerprints (`./gradlew :app:signingReport`).
2. Set the **Web** OAuth client ID in your user-level Gradle properties or via `-PGOOGLE_WEB_CLIENT_ID=...`:

   ```properties
   GOOGLE_WEB_CLIENT_ID=your-web-client-id.apps.googleusercontent.com
   ```

3. Rebuild. The same client ID is sent to the existing backend for ID-token audience verification. The iOS client ID is intentionally not reused.

See [Google's Credential Manager integration guide](https://developer.android.com/identity/sign-in/credential-manager-siwg-implementation).

## Structure

- `data`: account/session, exchange, roster, and gift-assignment models.
- `services`: listener-based `UserService` and `KrisKringleService`, sharing Volley `RemoteConnection` for form POST requests, authenticated photos, and multipart JPEG uploads.
- `ui`: Compose sign-in, exchange list/detail/editors, profile, and the system photo picker.

The app supports email codes, saved-session validation, exchange creation/updates, groups, adults and children, recipient draws and individual assignments, profile editing, logout, and account deletion. Apple authentication, the skeleton's follow/request features, password persistence, and Sugar ORM have been removed. Session preferences are excluded from backup and device transfer. Photo selection uses the system picker without storage permissions.

The API base URL is defined in `data/Constants.kt`. No backend or SwiftUI source changes are required by this port.

## Existing backend behavior

The roster endpoint currently returns every gift assignment to invited members as well as the creator. Android limits invited viewers to their own and their children’s recipient names in the UI. Enforcing that confidentiality against direct API access requires filtering the existing backend response; this Android port does not change the server.
