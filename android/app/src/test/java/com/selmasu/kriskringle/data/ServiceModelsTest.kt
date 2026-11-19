package com.selmasu.kriskringle.data

import com.selmasu.kriskringle.services.RemoteConnection
import com.selmasu.kriskringle.services.requireAccountDeletionResponse
import com.selmasu.kriskringle.services.requireGoogleLinkResponse
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

    @Test fun profileRetainsOptionalGoogleLinkStateWhenEditedOrUsingAnOlderBackend() {
        val legacy = MyProfile.fromJSON(JSONObject("""{"name":"Robin"}"""))
        assertNull(legacy.googleLinked)
        val linked = MyProfile.fromJSON(JSONObject("""{"name":"Robin","googleLinked":true}"""))
        assertEquals(true, linked.copy(name = "Rob").googleLinked)
        assertEquals(false, MyProfile.fromJSON(JSONObject("""{"name":"Robin","googleLinked":false}""")).googleLinked)
    }

    @Test fun onlyExplicitGoogleLinkConfirmationSucceeds() {
        requireGoogleLinkResponse(JSONObject("""{"googleLinked":true}"""))
        for (response in listOf("{}", "null", "false", "\"true\"")) {
            val json = if (response == "{}") JSONObject() else JSONObject("{\"googleLinked\":$response}")
            assertThrows(IllegalArgumentException::class.java) { requireGoogleLinkResponse(json) }
        }
    }

    @Test fun googleLinkFailurePreservesTheServersActionableMessage() {
        val error = assertThrows(IllegalArgumentException::class.java) {
            requireGoogleLinkResponse(JSONObject("""{"error":"This Google account matches another Kris Kringle account."}"""))
        }
        assertEquals("This Google account matches another Kris Kringle account.", error.message)
    }

    @Test fun formEncodingPreservesReservedCharactersAndNestedPhpFields() {
        val fields = linkedMapOf("data[name]" to "Mum & Dad + 家", "selmasuToken" to "a=b/c+")
        assertEquals("data%5Bname%5D=Mum+%26+Dad+%2B+%E5%AE%B6&selmasuToken=a%3Db%2Fc%2B",
            RemoteConnection.encodeFields(fields))
    }
}
