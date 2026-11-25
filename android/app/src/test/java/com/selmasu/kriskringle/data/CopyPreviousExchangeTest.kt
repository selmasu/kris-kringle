package com.selmasu.kriskringle.data

import com.selmasu.kriskringle.services.createKrisKringleFields
import org.junit.Assert.assertEquals
import org.junit.Assert.assertThrows
import org.junit.Test

class CopyPreviousExchangeTest {
    @Test fun availablePreviousYearsIncludeInvitedRecordsAndNewestRecordsAppearFirst() {
        val exchanges = listOf(
            KrisKringleSummary(1, "Older", 2023, 12),
            KrisKringleSummary(2, "Invited", 2025, 99),
            KrisKringleSummary(3, "Current", 2026, 12),
            KrisKringleSummary(4, "Family", 2025, 12),
            KrisKringleSummary(5, "Friends", 2025, 12),
            KrisKringleSummary(6, "Future", 2027, 12),
        )
        assertEquals(listOf(5, 4, 2, 1), previousExchangesForCopy(exchanges, 2026).map { it.id })
        assertEquals(emptyList<KrisKringleSummary>(), previousExchangesForCopy(exchanges, 2023))
    }

    @Test fun copyingSendsOnlyTheSourceAndNewNameInsteadOfAFirstGroupOrAllocations() {
        assertEquals(mapOf("name" to "Family", "copyFromId" to "42", "secret" to "1"),
            createKrisKringleFields("Family", "Unused group", 42))
        assertEquals(mapOf("name" to "Friends", "firstGroupName" to "First group", "secret" to "1"),
            createKrisKringleFields("Friends", "First group", null))
        assertThrows(IllegalArgumentException::class.java) { createKrisKringleFields("Family", "", 0) }
    }
}
