package com.selmasu.kriskringle.ui.exchanges

import androidx.compose.material3.MaterialTheme
import androidx.compose.ui.test.assert
import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.assertIsEnabled
import androidx.compose.ui.test.assertIsNotEnabled
import androidx.compose.ui.test.hasSetTextAction
import androidx.compose.ui.test.hasText
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.compose.ui.test.performTextInput
import androidx.test.ext.junit.runners.AndroidJUnit4
import com.selmasu.kriskringle.data.KrisKringleSummary
import java.util.Calendar
import org.junit.Assert.assertEquals
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith

/** Covers create/copy choices without creating exchanges or contacting the backend. */
@RunWith(AndroidJUnit4::class)
class CreateExchangeDialogTest {
    @get:Rule val compose = createComposeRule()

    private val currentYear = Calendar.getInstance().get(Calendar.YEAR)
    private val family = KrisKringleSummary(11, "Family", currentYear - 1, 42)
    private val work = KrisKringleSummary(7, "Work", currentYear - 2, 42)

    private fun show(previousExchanges: List<KrisKringleSummary>, onDismiss: () -> Unit = {}) {
        compose.setContent {
            MaterialTheme {
                CreateExchangeDialog(previousExchanges, onDismiss) { _, _ ->
                    error("The choice tests must not create an exchange.")
                }
            }
        }
    }

    @Test
    fun noPastRecordsOpensTheFreshFormDirectly() {
        show(emptyList())

        compose.onNodeWithText("Create Kris Kringle").assertIsDisplayed()
        compose.onNodeWithText("Copy a previous year?").assertDoesNotExist()
        compose.onNodeWithText("Name").assertIsDisplayed()
        compose.onNodeWithText("First group").assertIsDisplayed()
        compose.onNodeWithText("Create").assertIsNotEnabled()
    }

    @Test
    fun startingFreshRequiresANewNameAndFirstGroup() {
        show(listOf(family, work))

        compose.onNodeWithText("Copy a previous year?").assertIsDisplayed()
        compose.onNodeWithText("Start Fresh").performClick()
        compose.onNodeWithText("Name").performTextInput("New Family")
        compose.onNodeWithText("Create").assertIsNotEnabled()
        compose.onNodeWithText("First group").performTextInput("Household")
        compose.onNodeWithText("Create").assertIsEnabled()
        compose.onNodeWithText("Copying from ${family.year} · Family").assertDoesNotExist()
    }

    @Test
    fun choosingAPastRecordPrefillsItsNameAndOmitsTheDefaultGroup() {
        show(listOf(family, work))

        compose.onNodeWithText("Choose Year").performClick()
        compose.onNodeWithText("Copy from a previous year").assertIsDisplayed()
        compose.onNodeWithText("${family.year} · Family").assertIsDisplayed()
        compose.onNodeWithText("${work.year} · Work").performClick()

        compose.onNodeWithText("Copying from ${work.year} · Work").assertIsDisplayed()
        compose.onNode(hasSetTextAction()).assert(hasText("Work"))
        compose.onNodeWithText("First group").assertDoesNotExist()
        compose.onNodeWithText("Groups, members and children will be copied. Allocations won’t be copied.")
            .assertIsDisplayed()
        compose.onNodeWithText("Create").assertIsEnabled()
    }

    @Test
    fun cancellingTheNewFormDismissesWithoutCreatingAnExchange() {
        var dismissals = 0
        show(listOf(family)) { dismissals++ }

        compose.onNodeWithText("Start Fresh").performClick()
        compose.onNodeWithText("Cancel").performClick()

        compose.runOnIdle { assertEquals(1, dismissals) }
    }
}
