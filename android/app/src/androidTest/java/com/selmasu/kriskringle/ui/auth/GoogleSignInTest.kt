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
    fun accountLinkRequestIncludesItsSessionBoundNonce() {
        val nonce = googleLinkNonce(PUBLIC_SESSION, PUBLIC_SALT)
        val option = googleCredentialRequest(Constants.GOOGLE_WEB_CLIENT_ID, nonce)
            .credentialOptions.single() as GetSignInWithGoogleOption
        assertEquals(Constants.GOOGLE_WEB_CLIENT_ID, option.serverClientId)
        assertEquals(nonce, option.nonce)
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
    fun linkNonceMatchesBackendSha256AndBindsBothSessionAndSalt() {
        val nonce = googleLinkNonce(PUBLIC_SESSION, PUBLIC_SALT)
        assertEquals("cf94f408e86bb6c2497534025f21eb53597724975e64508840ab1f0140eb956a", nonce)
        assertTrue(nonce.matches(Regex("[0-9a-f]{64}")))
        assertNotEquals(nonce, googleLinkNonce("another-public-session", PUBLIC_SALT))
        assertNotEquals(nonce, googleLinkNonce(PUBLIC_SESSION, "00000000-0000-4000-8000-000000000002"))
        assertThrows(IllegalArgumentException::class.java) { googleLinkNonce("", PUBLIC_SALT) }
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

    private companion object {
        const val PUBLIC_SESSION = "public-test-session"
        const val PUBLIC_SALT = "00000000-0000-4000-8000-000000000001"
    }
}
