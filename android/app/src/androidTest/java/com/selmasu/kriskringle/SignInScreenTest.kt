package com.selmasu.kriskringle

import android.app.Application
import androidx.activity.ComponentActivity
import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.junit4.createAndroidComposeRule
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.compose.ui.test.performScrollTo
import androidx.compose.ui.test.performTextInput
import androidx.test.ext.junit.runners.AndroidJUnit4
import com.selmasu.kriskringle.data.Globals
import com.selmasu.kriskringle.data.User
import com.selmasu.kriskringle.ui.auth.AuthViewModel
import com.selmasu.kriskringle.ui.auth.LoginScreen
import com.selmasu.kriskringle.ui.theme.KrisKringleTheme
import org.junit.After
import org.junit.Assert.assertFalse
import org.junit.Before
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith

/** Exercises local validation and navigation without sending an email or contacting Google. */
@RunWith(AndroidJUnit4::class)
class SignInScreenTest {
    @get:Rule val compose = createAndroidComposeRule<ComponentActivity>()
    private lateinit var model: AuthViewModel
    private var previousUser: User? = null

    @Before
    fun showCleanSignIn() {
        compose.runOnUiThread {
            val application: Application = compose.activity.application
            Globals.initialize(application)
            previousUser = Globals.restoreSession()
            Globals.clearSession()
            model = AuthViewModel(application)
        }
        compose.setContent { KrisKringleTheme { LoginScreen(model) } }
    }

    @After
    fun restoreStoredSession() {
        compose.runOnUiThread {
            Globals.clearSession()
            previousUser?.let(Globals::saveUser)
        }
    }

    @Test
    fun invalidEmailStaysOnEmailFormWithoutStartingRequest() {
        compose.onNodeWithText("Continue with Email").performClick()
        compose.onNodeWithText("Email address").performTextInput("not-an-email")
        compose.onNodeWithText("Send Code").performScrollTo().performClick()

        compose.onNodeWithText("Please enter a valid email address.").performScrollTo().assertIsDisplayed()
        compose.onNodeWithText("6-digit code").assertDoesNotExist()
        compose.runOnIdle {
            assertFalse(model.busy)
            assertFalse(model.codeSent)
            assertFalse(model.signedIn)
        }
    }

    @Test
    fun cancelAndBackReturnToSignInChoicesAndClearFormErrors() {
        compose.onNodeWithText("Sign in with Apple").assertDoesNotExist()
        compose.onNodeWithText("Continue with Email").performClick()
        compose.onNodeWithText("Email address").performTextInput("invalid")
        compose.onNodeWithText("Send Code").performScrollTo().performClick()
        compose.onNodeWithText("Please enter a valid email address.").performScrollTo().assertIsDisplayed()
        compose.onNodeWithText("Cancel").performScrollTo().performClick()

        compose.onNodeWithText("Continue with Email").performScrollTo().assertIsDisplayed()
        compose.onNodeWithText("Sign in with Google").performScrollTo().assertIsDisplayed()
        compose.onNodeWithText("Please enter a valid email address.").assertDoesNotExist()

        compose.onNodeWithText("Continue with Email").performClick()
        compose.waitForIdle()
        compose.runOnUiThread { compose.activity.onBackPressedDispatcher.onBackPressed() }
        compose.onNodeWithText("Continue with Email").performScrollTo().assertIsDisplayed()
        compose.onNodeWithText("Sign in with Google").performScrollTo().assertIsDisplayed()
        compose.runOnIdle {
            assertFalse(model.emailMode)
            assertFalse(model.codeSent)
            assertFalse(model.busy)
        }
    }
}
