package com.selmasu.kriskringle.ui.exchanges

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.mutableStateOf
import androidx.compose.ui.test.assertCountEquals
import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.hasClickAction
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.compose.ui.test.onNodeWithText
import androidx.test.ext.junit.runners.AndroidJUnit4
import com.selmasu.kriskringle.data.KrisKringleChildGiftRecipient
import com.selmasu.kriskringle.data.KrisKringleSummary
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith

/** Checks roster privacy using the same visibility gate as the detail screen, without requests. */
@RunWith(AndroidJUnit4::class)
class SecretExchangeTest {
    @get:Rule val compose = createComposeRule()

    private val secret = KrisKringleSummary(
        id = 1, name = "Family", year = 2026, createdBy = 99,
        recipientName = "Taylor",
        childGiftRecipients = listOf(KrisKringleChildGiftRecipient(21, "Sam", "Morgan")),
        isSecret = true,
    )

    private fun show(group: KrisKringleSummary, viewerUserId: Int = 42) {
        compose.setContent { Content(group, viewerUserId) }
    }

    @androidx.compose.runtime.Composable
    private fun Content(group: KrisKringleSummary, viewerUserId: Int) {
        MaterialTheme {
            LazyColumn {
                exchangeRosterItems(group, viewerUserId) {
                    item {
                        Column {
                            Text("Private group")
                            Text("Other member")
                            Text("Other child")
                            Text("Other member’s recipient")
                        }
                    }
                }
            }
        }
    }

    @Test
    fun secretInvitedMembersSeeOnlyTheirOwnAndChildRecipientsWithoutEditActions() {
        val current = mutableStateOf(secret)
        compose.setContent { Content(current.value, 42) }

        compose.onNodeWithText("Your Gift Recipients").assertIsDisplayed()
        compose.onNodeWithText("You’re buying for").assertIsDisplayed()
        compose.onNodeWithText("Taylor").assertIsDisplayed()
        compose.onNodeWithText("Sam is buying for").assertIsDisplayed()
        compose.onNodeWithText("Morgan").assertIsDisplayed()
        assertRosterHidden()
        compose.onAllNodes(hasClickAction()).assertCountEquals(0)

        compose.runOnIdle { current.value = secret.copy(recipientName = null) }
        compose.onNodeWithText("You’re buying for").assertDoesNotExist()
        compose.onNodeWithText("Taylor").assertDoesNotExist()
        compose.onNodeWithText("Sam is buying for").assertIsDisplayed()
        compose.onNodeWithText("Morgan").assertIsDisplayed()
        assertRosterHidden()
    }

    @Test
    fun creatorsStillSeeTheFullRosterWhenSecretIsEnabled() {
        show(secret, viewerUserId = secret.createdBy)

        compose.onNodeWithText("Private group").assertIsDisplayed()
        compose.onNodeWithText("Other member").assertIsDisplayed()
        compose.onNodeWithText("Other child").assertIsDisplayed()
        compose.onNodeWithText("Other member’s recipient").assertIsDisplayed()
        compose.onNodeWithText("Your Gift Recipients").assertDoesNotExist()
    }

    @Test
    fun invitedMembersCanSeeTheRosterWhenSecretIsDisabled() {
        show(secret.copy(isSecret = false))

        compose.onNodeWithText("Private group").assertIsDisplayed()
        compose.onNodeWithText("Other member").assertIsDisplayed()
        compose.onNodeWithText("Other child").assertIsDisplayed()
        compose.onNodeWithText("Other member’s recipient").assertIsDisplayed()
        compose.onNodeWithText("Your Gift Recipients").assertDoesNotExist()
    }

    @Test
    fun unassignedSecretMembersSeeAnExplanationAndNoRoster() {
        show(secret.copy(recipientName = null, childGiftRecipients = emptyList()))

        compose.onNodeWithText("Your Gift Recipients").assertIsDisplayed()
        compose.onNodeWithText("Your gift recipients haven’t been assigned yet.").assertIsDisplayed()
        compose.onNodeWithText("You’re buying for").assertDoesNotExist()
        compose.onNodeWithText("Sam is buying for").assertDoesNotExist()
        assertRosterHidden()
        compose.onAllNodes(hasClickAction()).assertCountEquals(0)
    }

    private fun assertRosterHidden() {
        compose.onNodeWithText("Private group").assertDoesNotExist()
        compose.onNodeWithText("Other member").assertDoesNotExist()
        compose.onNodeWithText("Other child").assertDoesNotExist()
        compose.onNodeWithText("Other member’s recipient").assertDoesNotExist()
    }
}
