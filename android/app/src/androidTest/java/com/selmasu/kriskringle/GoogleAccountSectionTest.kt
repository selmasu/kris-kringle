package com.selmasu.kriskringle

import androidx.activity.ComponentActivity
import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.assertIsEnabled
import androidx.compose.ui.test.assertIsNotEnabled
import androidx.compose.ui.test.junit4.createAndroidComposeRule
import androidx.compose.ui.test.onNodeWithContentDescription
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.test.ext.junit.runners.AndroidJUnit4
import com.selmasu.kriskringle.ui.profile.GoogleAccountSection
import com.selmasu.kriskringle.ui.theme.KrisKringleTheme
import org.junit.Assert.assertEquals
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith

/** Verifies linking choices without opening Google or calling the account service. */
@RunWith(AndroidJUnit4::class)
class GoogleAccountSectionTest {
    @get:Rule val compose = createAndroidComposeRule<ComponentActivity>()

    @Test
    fun unknownLinkStatusOffersExplicitGoogleLink() {
        var requests = 0
        compose.setContent {
            KrisKringleTheme { GoogleAccountSection(googleLinked = null, linking = false, enabled = true) { requests++ } }
        }
        compose.onNodeWithContentDescription("Link Google Account").assertIsEnabled().performClick()
        compose.onNodeWithText("Link your Google account to use it to sign in to this Kris Kringle account.")
            .assertIsDisplayed()
        compose.runOnIdle { assertEquals(1, requests) }
    }

    @Test
    fun linkedAccountShowsStatusAndRemovesLinkAction() {
        compose.setContent {
            KrisKringleTheme { GoogleAccountSection(googleLinked = true, linking = false, enabled = true) {} }
        }
        compose.onNodeWithText("Google Account Linked").assertIsDisplayed()
        compose.onNodeWithContentDescription("Link Google Account").assertDoesNotExist()
    }

    @Test
    fun linkingDisablesRepeatedRequestsAndShowsProgress() {
        compose.setContent {
            KrisKringleTheme { GoogleAccountSection(googleLinked = false, linking = true, enabled = true) {} }
        }
        compose.onNodeWithContentDescription("Link Google Account").assertIsNotEnabled()
        compose.onNodeWithText("Linking Google account…").assertIsDisplayed()
    }
}
