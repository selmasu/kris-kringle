package com.selmasu.kriskringle.data

import com.selmasu.kriskringle.services.createKrisKringleFields
import com.selmasu.kriskringle.services.updateKrisKringleFields
import org.json.JSONObject
import org.junit.Assert.*
import org.junit.Test

class SecretKrisKringleTest {
    private fun summary(secret: Any? = null): KrisKringleSummary {
        val json = JSONObject("""{"krisKringleId":1,"name":"Family","year":2026,"createdBy":12}""")
        if (secret != null) json.put("secret", secret)
        return KrisKringleSummary.fromJSON(json)
    }

    @Test fun readsSecretFromApiBooleansAndDatabaseNumericValuesWithoutChangingLegacyRecords() {
        for (value in listOf(true, 1, "1", "true")) {
            assertTrue("Secret value $value should be enabled", summary(value).isSecret)
        }
        for (value in listOf(false, 0, "0", "false", JSONObject.NULL)) {
            assertFalse("Secret value $value should be disabled", summary(value).isSecret)
        }
        assertFalse(summary().isSecret)
        assertTrue(summary(true).copy(name = "New name").isSecret)
    }

    @Test fun freshAndCopiedExchangesDefaultToSecretButCanBeExplicitlyOpen() {
        assertEquals(mapOf("name" to "Family", "firstGroupName" to "Household", "secret" to "1"),
            createKrisKringleFields("Family", "Household", null))
        assertEquals(mapOf("name" to "Family", "copyFromId" to "42", "secret" to "1"),
            createKrisKringleFields("Family", "", 42))
        assertEquals(mapOf("name" to "Family", "firstGroupName" to "Household", "secret" to "0"),
            createKrisKringleFields("Family", "Household", null, isSecret = false))
        assertEquals(mapOf("name" to "Family", "copyFromId" to "42", "secret" to "0"),
            createKrisKringleFields("Family", "", 42, isSecret = false))
    }

    @Test fun secretOnlyChangesCanBeSavedAndOtherEditsDoNotResetTheSetting() {
        assertEquals(mapOf("id" to "7", "secret" to "1"), updateKrisKringleFields(7, null, true))
        assertEquals(mapOf("id" to "7", "secret" to "0"), updateKrisKringleFields(7, null, false))
        assertEquals(mapOf("id" to "7", "name" to "New name"), updateKrisKringleFields(7, "New name", null))
        assertEquals(mapOf("id" to "7"), updateKrisKringleFields(7, null, null))
    }
}
