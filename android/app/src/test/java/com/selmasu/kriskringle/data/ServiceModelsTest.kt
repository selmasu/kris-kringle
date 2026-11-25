package com.selmasu.kriskringle.data

import com.selmasu.kriskringle.services.RemoteConnection
import com.selmasu.kriskringle.services.requireAccountDeletionResponse
import org.json.JSONObject
import org.junit.Assert.*
import org.junit.Test

class ServiceModelsTest {
    @Test fun accountNameFallsBackAcrossBlankAndNullServerFields() {
        val json = JSONObject("""{"userId":"12","firstName":" Robin ","displayName":" ","name":null,"email":"robin@example.com"}""")
        val user = User.fromJSON(json, "session-token")
        assertEquals(12, user.userId)
        assertEquals("Robin", user.name)
        assertEquals("", user.picture)
        assertEquals(user, User.fromJSON(user.toJSON()))
        assertFalse(user.toJSON().has("password"))
    }

    @Test fun exchangeSupportsNumericDatabaseStringsAndAbsentRecipient() {
        val group = KrisKringleSummary.fromJSON(JSONObject("""{"krisKringleId":"4","name":"","year":"2026","createdBy":"12","recipientName":null}"""))
        assertEquals(4, group.id)
        assertEquals("Kris Kringle", group.name)
        assertEquals(2026, group.year)
        assertNull(group.recipientName)
    }

    @Test fun childGiftRecipientsSurviveWithoutParentsOwnAssignment() {
        val group = KrisKringleSummary.fromJSON(JSONObject("""{
            "krisKringleId":4,"name":"Exchange","year":2026,"createdBy":12,
            "childGiftRecipients":[
                {"memberId":21,"childName":"Sam","recipientName":"Alex"},
                {"memberId":22,"childName":"Sam","recipientName":"Taylor"}
            ]
        }"""))
        assertNull(group.recipientName)
        assertEquals(listOf(21, 22), group.childGiftRecipients.map { it.id })
        assertEquals(listOf("Alex", "Taylor"), group.childGiftRecipients.map { it.recipientName })
        assertEquals(listOf("Sam", "Sam"), group.childGiftRecipients.map { it.childName })
        assertEquals(group.childGiftRecipients, group.copy(name = "Renamed").childGiftRecipients)
        val legacy = KrisKringleSummary.fromJSON(JSONObject("""{"krisKringleId":5,"name":"Old Server","year":2026,"createdBy":12}"""))
        assertTrue(legacy.childGiftRecipients.isEmpty())
    }

    @Test(expected = IllegalArgumentException::class)
    fun rejectsMalformedExchangeInsteadOfDisplayingInvalidNavigationTarget() {
        KrisKringleSummary.fromJSON(JSONObject("""{"krisKringleId":0,"name":"Family","year":2026,"createdBy":12}"""))
    }

    @Test fun rosterKeepsAdultUserIdSeparateFromGiftMemberIdAndIncludesChildren() {
        val roster = KrisKringleRoster.fromJSON(JSONObject("""{
            "groups":[{"groupId":2,"name":"Family"}],
            "members":[{"userId":12,"memberId":"21","groupId":2,"name":"Robin","email":null,
                "pictureURL":"file:///private/photo.jpg","isCreator":true,
                "children":[{"memberId":22,"name":"Sam"}]}],
            "assignments":[{"giverId":21,"recipientName":"Sam"}]
        }"""))
        val adult = roster.members.single()
        assertEquals(12, adult.id)
        assertEquals(21, adult.memberId)
        assertNull(adult.pictureURL)
        assertFalse(adult.hasPicture)
        assertEquals(22, adult.children.single().id)
        assertEquals("Sam", roster.assignments[21]?.recipientName)
        assertEquals("giverId" to "21", KrisKringleGiftGiverID.Member(21).parameter)
        assertEquals("giverUserId" to "12", KrisKringleGiftGiverID.User(12).parameter)
    }

    @Test fun noGiftAssignmentIsRepresentedAsNull() {
        val options = KrisKringleGiftOptions.fromJSON(JSONObject("""{
            "options":[{"memberId":22,"groupId":3,"name":"Sam"}],
            "recipientId":null,"recipientName":null
        }"""))
        assertNull(options.recipientId)
        assertNull(options.recipientName)
        assertEquals(22, options.recipients.single().id)
    }

    @Test fun honorsFrameworkErrorMessageAndDoesNotTreatNullOrBooleanAsErrors() {
        assertEquals("Please sign in again.", RemoteConnection.serverError(
            JSONObject("""{"errorMessage":"Please sign in again."}""")))
        assertEquals("Readable explanation", RemoteConnection.serverError(
            JSONObject("""{"error":"internalCode","errorMessage":"Readable explanation"}""")))
        assertNull(RemoteConnection.serverError(JSONObject("""{"error":false,"errorMessage":null}""")))
    }

    @Test fun phpVoidAccountDeletionResponseSucceeds() {
        requireAccountDeletionResponse(RemoteConnection.decodeResponse("null".toByteArray(), allowNull = true))
        requireAccountDeletionResponse(RemoteConnection.decodeResponse(byteArrayOf(), allowNull = true))
    }

    @Test(expected = IllegalArgumentException::class)
    fun anUnexpectedObjectCannotClearTheAccountSessionAsADeletionSuccess() {
        requireAccountDeletionResponse(RemoteConnection.decodeResponse("{}".toByteArray(), allowNull = true))
    }

    @Test fun formEncodingPreservesReservedCharactersAndNestedPhpFields() {
        val fields = linkedMapOf("data[name]" to "Mum & Dad + 家", "selmasuToken" to "a=b/c+")
        assertEquals("data%5Bname%5D=Mum+%26+Dad+%2B+%E5%AE%B6&selmasuToken=a%3Db%2Fc%2B",
            RemoteConnection.encodeFields(fields))
    }
}
