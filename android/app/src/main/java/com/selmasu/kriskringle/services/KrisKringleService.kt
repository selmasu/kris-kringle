package com.selmasu.kriskringle.services

import android.content.Context
import com.selmasu.kriskringle.data.*
import org.json.JSONObject

/** Callback/listener service, using the same RemoteConnection pattern as UserService. */
class KrisKringleService(private val listener: KrisKringleServiceListener, private val context: Context) {
    enum class Type {
        LIST, CREATE, DETAIL, UPDATE, ADD_GROUP, ADD_MEMBER, UPDATE_MEMBER, ADD_CHILD,
        UPDATE_GIFT_RECIPIENTS, GIFT_OPTIONS, UPDATE_GIFT_ASSIGNMENT,
    }
    private val connections = mutableListOf<RemoteConnection>()

    fun list() = request(Type.LIST, "getKrisKringles") {
        KrisKringleServiceData(groups = (it.optJSONArray("data")
            ?: throw IllegalArgumentException("The server didn’t return a list of Kris Kringles."))
            .objects().map(KrisKringleSummary::fromJSON))
    }

    fun create(name: String, firstGroupName: String, picture: ByteArray? = null) =
        request(Type.CREATE, "createKrisKringle", mapOf("name" to name, "firstGroupName" to firstGroupName), picture) {
            KrisKringleServiceData(summary = KrisKringleSummary.fromJSON(it.getJSONObject("data")))
        }

    fun detail(id: Int) = request(Type.DETAIL, "getKrisKringle", idFields(id)) {
        KrisKringleServiceData(summary = KrisKringleSummary.fromJSON(it.getJSONObject("data")),
            roster = KrisKringleRoster.fromJSON(it))
    }

    fun update(id: Int, name: String?, picture: ByteArray?) = request(Type.UPDATE, "updateKrisKringle",
        idFields(id) + (name?.let { mapOf("name" to it) } ?: emptyMap()), picture) {
        KrisKringleServiceData(summary = KrisKringleSummary.fromJSON(it.getJSONObject("data")))
    }

    fun addGroup(id: Int, name: String) = request(Type.ADD_GROUP, "addKrisKringleGroup",
        idFields(id) + ("name" to name)) {
        KrisKringleServiceData(groupId = it.requiredId("groupId", "The server didn’t return the new group."),
            roster = KrisKringleRoster.fromJSON(it))
    }

    fun addMember(id: Int, groupId: Int, email: String, firstName: String) =
        rosterRequest(Type.ADD_MEMBER, "addKrisKringleMember", idFields(id) + mapOf(
            "groupId" to groupId.toString(), "email" to email, "firstName" to firstName))

    fun updateMember(id: Int, memberId: Int?, userId: Int, firstName: String, email: String) =
        rosterRequest(Type.UPDATE_MEMBER, "updateKrisKringleMember", idFields(id) + mapOf(
            "expectedUserId" to userId.toString(), "firstName" to firstName, "email" to email) +
            if (memberId == null) mapOf("userId" to userId.toString()) else mapOf("memberId" to memberId.toString()))

    fun addChild(id: Int, parentUserId: Int, name: String) =
        rosterRequest(Type.ADD_CHILD, "addKrisKringleChild", idFields(id) + mapOf(
            "parentUserId" to parentUserId.toString(), "name" to name))

    fun updateAllGiftRecipients(id: Int, clear: Boolean) = rosterRequest(Type.UPDATE_GIFT_RECIPIENTS,
        if (clear) "clearGiftRecipients" else "setGiftRecipients", idFields(id))

    fun giftRecipientOptions(id: Int, giverId: KrisKringleGiftGiverID) =
        request(Type.GIFT_OPTIONS, "getGiftRecipientOptions", idFields(id) + giverId.parameter) {
            KrisKringleServiceData(giftOptions = KrisKringleGiftOptions.fromJSON(it))
        }

    fun updateGiftAssignment(id: Int, giverId: KrisKringleGiftGiverID, recipientId: Int?, expectedRecipientId: Int?) =
        rosterRequest(Type.UPDATE_GIFT_ASSIGNMENT, "updateGiftAssignment", idFields(id) + giverId.parameter + mapOf(
            "recipientId" to (recipientId ?: 0).toString(), "expectedRecipientId" to (expectedRecipientId ?: 0).toString()))

    fun picture(id: Int, onSuccess: (ByteArray) -> Unit, onError: (String) -> Unit) =
        connection().requestBytes("krisKringles", "krisKringles", "getKrisKringlePicture", idFields(id), onSuccess, onError)

    fun memberPicture(id: Int, userId: Int, onSuccess: (ByteArray) -> Unit, onError: (String) -> Unit) =
        connection().requestBytes("krisKringles", "krisKringles", "getKrisKringleMemberPicture",
            idFields(id) + ("userId" to userId.toString()), onSuccess, onError)

    fun cancel() { connections.forEach(RemoteConnection::cancel); connections.clear() }

    private fun idFields(id: Int) = mapOf("id" to id.toString())
    private fun rosterRequest(type: Type, function: String, fields: Map<String, String>) =
        request(type, function, fields) { KrisKringleServiceData(roster = KrisKringleRoster.fromJSON(it)) }

    private fun request(type: Type, function: String, fields: Map<String, String> = emptyMap(),
                        picture: ByteArray? = null, parse: (JSONObject) -> KrisKringleServiceData) {
        connection { json ->
            val data = runCatching { parse(json) }
            data.fold({ listener.krisKringleServiceDone(type, it) }, {
                listener.serviceError(it.message ?: "The server returned an unexpected response. Please try again.")
            })
        }.requestObject("krisKringles", "krisKringles", function, fields, picture)
    }

    private fun connection(complete: (JSONObject) -> Unit = {}): RemoteConnection =
        RemoteConnection(object : RemoteConnectionListener {
            override fun objectRequestComplete(data: JSONObject) = complete(data)
            override fun remoteConnectionError(message: String) = listener.serviceError(message)
        }, context).also(connections::add)
}
