# Kris Kringle for Android

Jetpack Compose version of the SwiftUI app, using the existing Kris Kringle backend.

## Build

Open this directory in Android Studio, or run with JDK 25 and Android SDK 37 installed:

```sh
./gradlew :app:assembleDebug :app:testDebugUnitTest :app:lintDebug
```

The debug APK is `app/build/outputs/apk/debug/app-debug.apk`.
Run `./gradlew :app:connectedDebugAndroidTest` with an emulator to check sign-in navigation and validation without sending an email.

When creating a Kris Kringle, Android offers to copy an available KK from a previous year, including ones you were invited to. Choose a source or start fresh. Copying preserves its groups, members and children, and starts with no gift allocations. The new name and optional photo are entered separately. This requires deploying the `copyFromId` support in the Kris Kringle backend service, API and model together with the Android update.

New KKs default to **Secret**, matching iOS. The creator can change this when creating a KK or in its details, then choose **Save changes**. Invited members of secret KKs see only their own and their children’s gift recipients; the full roster stays visible to the creator. Existing KKs retain their saved setting. This uses the shared backend’s `secret` field and recipient-only detail response.

## Google sign-in

The app uses Android Credential Manager and the Web OAuth client ID in `data/Constants.kt`. That same audience is sent to the backend's `loginGoogle` endpoint. No client secret is embedded in the app.

- **Sign in with Google** signs in to a linked account. The backend can also match an existing account through a verified Gmail or managed Workspace address.
- Accounts that cannot be matched automatically can continue with their invitation email using a one-time code.
- Logout clears the server session and Credential Manager's active credential state.

The configured Web client is `255479378047-8e98vs8lm7kar4r641g9hctk4vn594ij.apps.googleusercontent.com`.
Google Cloud must also have an Android OAuth client for `com.selmasu.kriskringle` in the same project. Keep the Web client ID in `Constants.kt`; the Android client ID stays in Google Cloud and is matched automatically using the package and signing certificate. The registered debug certificate SHA-1 on this machine is:

```text
73:2E:53:6E:0D:E1:65:B3:42:81:C0:2D:35:64:89:02:F1:F3:7F:9F
```

Use `./gradlew :app:signingReport` for other signing certificates. Register the release or Play App Signing certificate before shipping a release build.

The backend allowlist in `core/services/user/UserService.php` includes both the iOS client and this Web client. Deploy that backend configuration with the existing Google authentication endpoint before testing against production.

See [Google's Credential Manager integration guide](https://developer.android.com/identity/sign-in/credential-manager-siwg-implementation).

## Structure

- `data`: account/session, exchange, roster, and gift-assignment models.
- `services`: listener-based `UserService` and `KrisKringleService`, sharing Volley `RemoteConnection` for form POST requests, authenticated photos, and multipart JPEG uploads.
- `ui`: Compose sign-in, exchange list/detail/editors, profile, and the system photo picker.

The app supports email codes, saved-session validation, exchange creation/updates, groups, adults and children, recipient draws and individual assignments, profile editing, logout, and account deletion. Apple authentication, the skeleton's follow/request features, password persistence, and Sugar ORM have been removed. Session preferences are excluded from backup and device transfer. Photo selection uses the system picker without storage permissions.

The API base URL is defined in `data/Constants.kt`. Google sign-in additionally uses the backend client allowlist described above; SwiftUI source is unchanged by the Android implementation.

## Gift allocation visibility

For non-secret KKs, all members can see the full roster and every gift allocation. Only the creator can edit them. For secret KKs, invited members see only their own and their children’s gift recipients; the backend omits the full roster and other allocations from their responses.
