package com.selmasu.kriskringle.ui.exchanges

import androidx.compose.foundation.layout.Column
import androidx.compose.material3.MaterialTheme
import androidx.compose.ui.test.*
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.test.ext.junit.runners.AndroidJUnit4
import com.selmasu.kriskringle.data.KrisKringleGiftAssignment
import com.selmasu.kriskringle.data.KrisKringleGiftGiverID
import com.selmasu.kriskringle.data.KrisKringleRoster
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith

/** Recipient access is checked at the rendered UI, without issuing backend requests. */
@RunWith(AndroidJUnit4::class)
class GiftAssignmentPrivacyTest {
    @get:Rule val compose = createComposeRule()

    private val roster = KrisKringleRoster(emptyList(), emptyList(), mapOf(
        5 to KrisKringleGiftAssignment(5, "Taylor"),
        6 to KrisKringleGiftAssignment(6, "Morgan"),
        7 to KrisKringleGiftAssignment(7, "Casey"),
        8 to KrisKringleGiftAssignment(8, "Riley")
    ))

    @Test fun nonSecretInvitedMemberSeesEveryAllocationWithoutEditActions() {
        compose.setContent {
            MaterialTheme {
                Column {
                    GiftAssignmentRow(GiftGiver(KrisKringleGiftGiverID.Member(5), "Me"), roster, false, true) {}
                    GiftAssignmentRow(GiftGiver(KrisKringleGiftGiverID.Member(6), "My child"), roster, false, true) {}
                    GiftAssignmentRow(GiftGiver(KrisKringleGiftGiverID.Member(7), "Other member"), roster, false, true) {}
                    GiftAssignmentRow(GiftGiver(KrisKringleGiftGiverID.Member(8), "Other child"), roster, false, true) {}
                }
            }
        }
        compose.onNodeWithText("🎁 Buying for Taylor").assertHasNoClickAction()
        compose.onNodeWithText("🎁 Buying for Morgan").assertHasNoClickAction()
        compose.onNodeWithText("🎁 Buying for Casey").assertHasNoClickAction()
        compose.onNodeWithText("🎁 Buying for Riley").assertHasNoClickAction()
        compose.onNodeWithText("🎁 Choose recipient").assertDoesNotExist()
    }

    @Test fun creatorCanManageOtherMembersRecipients() {
        var openedEditor = false
        compose.setContent {
            MaterialTheme {
                GiftAssignmentRow(GiftGiver(KrisKringleGiftGiverID.Member(7), "Other member"), roster, true, true) { openedEditor = true }
            }
        }
        compose.onNodeWithText("🎁 Buying for Casey").assertHasClickAction().performClick()
        compose.runOnIdle { assertTrue(openedEditor) }
    }

    @Test fun invitedMemberCannotAllocateAnUnassignedGift() {
        compose.setContent {
            MaterialTheme {
                GiftAssignmentRow(GiftGiver(KrisKringleGiftGiverID.Member(9), "Me"), roster, false, true) {}
            }
        }
        compose.onNodeWithText("🎁 Choose recipient").assertDoesNotExist()
    }
}
