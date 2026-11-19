# Kris Kringle for Android

Jetpack Compose version of the SwiftUI app, using the existing Kris Kringle backend.

## Build

Open this directory in Android Studio, or run with JDK 25 and Android SDK 37 installed:

```sh
./gradlew :app:assembleDebug :app:testDebugUnitTest :app:lintDebug
```

The debug APK is `app/build/outputs/apk/debug/app-debug.apk`.
Run `./gradlew :app:connectedDebugAndroidTest` with an emulator to check sign-in navigation and validation without sending an email.

## Google sign-in

The app uses Android Credential Manager and the Web OAuth client ID in `data/Constants.kt`. That same audience is sent to the backend's `loginGoogle` and `linkGoogleAccount` endpoints. No client secret is embedded in the app.

- **Sign in with Google** signs in to a linked account. The backend can also match an existing account through a verified Gmail or managed Workspace address.
- Other unlinked accounts continue with their invitation email, then choose **Me → Link Google Account**. Linking binds the Google authorization to the current Kris Kringle session using a SHA-256 nonce, and keeps the current account and profile drafts.
- Logout clears the server session and Credential Manager's active credential state.

The configured Web client is `255479378047-8e98vs8lm7kar4r641g9hctk4vn594ij.apps.googleusercontent.com`.
Google Cloud must also have an Android OAuth client for `com.selmasu.kriskringle` in the same project. The registered debug certificate SHA-1 on this machine is:

```text
73:2E:53:6E:0D:E1:65:B3:42:81:C0:2D:35:64:89:02:F1:F3:7F:9F
```

Use `./gradlew :app:signingReport` for other signing certificates. Register the release or Play App Signing certificate before shipping a release build.

The backend allowlist in `core/services/user/UserService.php` includes both the iOS client and this Web client. Deploy that backend configuration with the existing Google authentication/linking endpoints before testing against production.

See [Google's Credential Manager integration guide](https://developer.android.com/identity/sign-in/credential-manager-siwg-implementation).

## Structure

- `data`: account/session, exchange, roster, and gift-assignment models.
- `services`: listener-based `UserService` and `KrisKringleService`, sharing Volley `RemoteConnection` for form POST requests, authenticated photos, and multipart JPEG uploads.
- `ui`: Compose sign-in, exchange list/detail/editors, profile, and the system photo picker.

The app supports email codes, saved-session validation, exchange creation/updates, groups, adults and children, recipient draws and individual assignments, profile editing, logout, and account deletion. Apple authentication, the skeleton's follow/request features, password persistence, and Sugar ORM have been removed. Session preferences are excluded from backup and device transfer. Photo selection uses the system picker without storage permissions.

The API base URL is defined in `data/Constants.kt`. Google sign-in additionally uses the backend client allowlist described above; SwiftUI source is unchanged by the Android implementation.

## Existing backend behavior

The roster endpoint currently returns every gift assignment to invited members as well as the creator. Android limits invited viewers to their own and their children’s recipient names in the UI. Enforcing that confidentiality against direct API access requires filtering the existing backend response; this Android port does not change the server.
