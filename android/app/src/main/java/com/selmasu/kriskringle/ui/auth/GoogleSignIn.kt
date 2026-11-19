package com.selmasu.kriskringle.ui.auth

import android.content.Context
import android.content.MutableContextWrapper
import androidx.credentials.CredentialManager
import androidx.credentials.CustomCredential
import androidx.credentials.GetCredentialRequest
import com.google.android.libraries.identity.googleid.GetSignInWithGoogleOption
import com.google.android.libraries.identity.googleid.GoogleIdTokenCredential

/** The Web OAuth client ID is also the audience the backend verifies. */
suspend fun googleIdToken(activityContext: Context, webClientId: String): String {
    require(webClientId.isNotBlank()) {
        "Google sign-in needs a Web OAuth client ID. Set GOOGLE_WEB_CLIENT_ID in Gradle properties and rebuild."
    }
    val option = GetSignInWithGoogleOption.Builder(webClientId).build()
    val request = GetCredentialRequest.Builder().addCredentialOption(option).build()
    val credential = CredentialManager.create(activityContext).getCredential(
        context = MutableContextWrapper(activityContext), request = request
    ).credential
    require(credential is CustomCredential &&
        credential.type == GoogleIdTokenCredential.TYPE_GOOGLE_ID_TOKEN_CREDENTIAL) {
        "Google returned an unsupported credential. Please try again."
    }
    return GoogleIdTokenCredential.createFrom(credential.data).idToken
}
