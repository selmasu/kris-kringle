package com.selmasu.kriskringle.ui.auth

import android.os.Bundle
import android.util.Base64
import androidx.credentials.CustomCredential
import androidx.credentials.exceptions.GetCredentialCancellationException
import androidx.credentials.exceptions.GetCredentialProviderConfigurationException
import androidx.credentials.exceptions.GetCredentialUnsupportedException
import androidx.credentials.exceptions.NoCredentialException
import androidx.test.ext.junit.runners.AndroidJUnit4
import com.google.android.libraries.identity.googleid.GetSignInWithGoogleOption
import com.google.android.libraries.identity.googleid.GoogleIdTokenCredential
import com.google.android.libraries.identity.googleid.GoogleIdTokenParsingException
import com.selmasu.kriskringle.data.Constants
import org.junit.Assert.*
import org.junit.Test
import org.junit.runner.RunWith

/** Exercises request/credential contracts locally, without opening a picker or contacting Google. */
@RunWith(AndroidJUnit4::class)
class GoogleSignInTest {
    @Test
    fun signInRequestUsesConfiguredWebAudienceWithoutRequiringAPreviouslyAuthorizedAccount() {
        val request = googleCredentialRequest(Constants.GOOGLE_WEB_CLIENT_ID)
        val option = request.credentialOptions.single()
        assertTrue(option is GetSignInWithGoogleOption)
        option as GetSignInWithGoogleOption
        assertEquals(Constants.GOOGLE_WEB_CLIENT_ID, option.serverClientId)
        assertNull(option.nonce)
    }

    @Test
    fun blankAudienceCannotOpenAGoogleRequest() {
        assertThrows(IllegalArgumentException::class.java) { googleCredentialRequest("  ") }
    }

    @Test
    fun googleSdkCredentialBundleReturnsOnlyTheIdTokenForBackendVerification() {
        // Unsigned public parser fixture; it is never submitted to the backend.
        val token = listOf(
            """{"alg":"none","typ":"JWT"}""",
            """{"sub":"public-fixture-subject","email":"public-fixture@example.com"}""",
            "not-a-real-signature",
        ).joinToString(".") {
            Base64.encodeToString(it.toByteArray(Charsets.UTF_8), Base64.URL_SAFE or Base64.NO_PADDING or Base64.NO_WRAP)
        }
        val sdkCredential = GoogleIdTokenCredential.Builder()
            .setId("public-fixture@example.com")
            .setIdToken(token)
            .setDisplayName("Public Fixture")
            .build()
        val providerCredential = CustomCredential(
            GoogleIdTokenCredential.TYPE_GOOGLE_ID_TOKEN_CREDENTIAL,
            sdkCredential.data,
        )
        assertEquals(token, googleCredentialToken(providerCredential))
    }

    @Test
    fun unsupportedCredentialsAndMalformedGoogleBundlesCannotSignIn() {
        assertThrows(IllegalArgumentException::class.java) {
            googleCredentialToken(CustomCredential("example.unsupported.credential", Bundle()))
        }
        assertThrows(GoogleIdTokenParsingException::class.java) {
            googleCredentialToken(CustomCredential(GoogleIdTokenCredential.TYPE_GOOGLE_ID_TOKEN_CREDENTIAL, Bundle()))
        }
    }

    @Test
    fun dismissingTheGooglePickerDoesNotPresentAnError() {
        assertNull(googleSignInErrorMessage(GetCredentialCancellationException()))
    }

    @Test
    fun missingAccountOffersAnEmailAlternative() {
        val message = googleSignInErrorMessage(NoCredentialException()).orEmpty()
        assertTrue(message.contains("Add an account"))
        assertTrue(message.contains("continue with email"))
    }

    @Test
    fun unavailableProviderExplainsHowToRecover() {
        val missingProvider = googleSignInErrorMessage(GetCredentialProviderConfigurationException())
        assertEquals(missingProvider, googleSignInErrorMessage(GetCredentialUnsupportedException()))
        assertTrue(missingProvider.orEmpty().contains("Google Play services"))
        assertTrue(missingProvider.orEmpty().contains("continue with email"))
    }

    @Test
    fun invalidAndUnexpectedResponsesHaveReadableErrorsWithoutExceptionDetails() {
        assertEquals("Google returned an invalid sign-in response. Please try again.",
            googleSignInErrorMessage(GoogleIdTokenParsingException(IllegalArgumentException("public-fixture-invalid-token"))))
        val message = googleSignInErrorMessage(IllegalStateException("internal-fixture-detail")).orEmpty()
        assertTrue(message.contains("continue with email"))
        assertFalse(message.contains("internal-fixture-detail"))
    }

}
