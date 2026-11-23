package com.selmasu.kriskringle.ui.auth

import android.app.Activity
import android.content.Context
import android.content.ContextWrapper
import android.content.MutableContextWrapper
import androidx.credentials.Credential
import androidx.credentials.CredentialManager
import androidx.credentials.CustomCredential
import androidx.credentials.GetCredentialRequest
import androidx.credentials.exceptions.GetCredentialCancellationException
import androidx.credentials.exceptions.GetCredentialProviderConfigurationException
import androidx.credentials.exceptions.GetCredentialUnsupportedException
import androidx.credentials.exceptions.NoCredentialException
import com.google.android.libraries.identity.googleid.GetSignInWithGoogleOption
import com.google.android.libraries.identity.googleid.GoogleIdTokenCredential
import com.google.android.libraries.identity.googleid.GoogleIdTokenParsingException
import com.selmasu.kriskringle.data.Constants

/** The Web OAuth client ID is also the audience the backend verifies. */
suspend fun googleIdToken(
    activityContext: Context,
    webClientId: String = Constants.GOOGLE_WEB_CLIENT_ID
): String {
    val request = googleCredentialRequest(webClientId)
    val activity = activityContext.signInActivity()
        ?: throw IllegalStateException("Google sign-in needs an active screen.")
    val credential = CredentialManager.create(activity).getCredential(
        context = MutableContextWrapper(activity), request = request
    ).credential
    return googleCredentialToken(credential)
}

internal fun googleCredentialRequest(webClientId: String): GetCredentialRequest {
    require(webClientId.isNotBlank()) {
        "Google sign-in is not configured. Please continue with email."
    }
    val option = GetSignInWithGoogleOption.Builder(webClientId).build()
    return GetCredentialRequest.Builder().addCredentialOption(option).build()
}

internal fun googleCredentialToken(credential: Credential): String {
    require(credential is CustomCredential &&
        credential.type == GoogleIdTokenCredential.TYPE_GOOGLE_ID_TOKEN_CREDENTIAL) {
        "Google returned an unsupported credential. Please try again."
    }
    val token = GoogleIdTokenCredential.createFrom(credential.data).idToken
    require(token.isNotBlank()) { "Google did not return a sign-in token. Please try again." }
    return token
}

/** Cancellation returns to the current screen without presenting an error. */
fun googleSignInErrorMessage(error: Exception): String? = when (error) {
    is GetCredentialCancellationException -> null
    is NoCredentialException -> "No Google account is available. Add an account or continue with email."
    is GetCredentialProviderConfigurationException, is GetCredentialUnsupportedException ->
        "Google sign-in is unavailable on this device. Update Google Play services or continue with email."
    is GoogleIdTokenParsingException -> "Google returned an invalid sign-in response. Please try again."
    else -> if (Constants.GOOGLE_WEB_CLIENT_ID.isBlank())
        "Google sign-in is not configured. Please continue with email."
    else "Couldn’t sign in with Google. Please try again or continue with email."
}

private fun Context.signInActivity(): Activity? = when (this) {
    is Activity -> this
    is ContextWrapper -> if (baseContext !== this) baseContext.signInActivity() else null
    else -> null
}
