package com.selmasu.kriskringle.data

import org.json.JSONArray
import org.json.JSONObject

/** The exchange and roster models mirror the Swift app and the existing PHP API. */
data class KrisKringleSummary(
    val id: Int,
    val name: String,
    val year: Int,
    val createdBy: Int,
    val pictureVersion: String = "",
    val recipientName: String? = null,
    val childGiftRecipients: List<KrisKringleChildGiftRecipient> = emptyList(),
    val isSecret: Boolean = false,
) {
    companion object {
        fun fromJSON(json: JSONObject): KrisKringleSummary {
            val error = "The server returned an invalid Kris Kringle."
            require(json.has("year") && json.has("createdBy")) { error }
            return KrisKringleSummary(json.requiredId("krisKringleId", error),
                json.requiredString("name", error).ifEmpty { "Kris Kringle" },
                json.getInt("year"), json.getInt("createdBy"), json.string("pictureVersion"),
                json.nullableString("recipientName"),
                json.optJSONArray("childGiftRecipients")?.objects()?.map(KrisKringleChildGiftRecipient::fromJSON) ?: emptyList(),
                json.optBoolean("secret", false) || json.optInt("secret", 0) != 0)
        }
    }
}

data class KrisKringleChildGiftRecipient(val id: Int, val childName: String, val recipientName: String) {
    companion object {
        fun fromJSON(json: JSONObject): KrisKringleChildGiftRecipient {
            val error = "The server returned an invalid child gift assignment."
            return KrisKringleChildGiftRecipient(json.requiredId("memberId", error),
                json.requiredString("childName", error), json.requiredString("recipientName", error))
        }
    }
}

/** The list already contains only exchanges available to the signed-in account. */
internal fun previousExchangesForCopy(
    exchanges: List<KrisKringleSummary>, currentYear: Int
): List<KrisKringleSummary> = exchanges.filter { it.year < currentYear }
    .sortedWith(compareByDescending<KrisKringleSummary> { it.year }.thenByDescending { it.id })

data class KrisKringleGroup(val id: Int, val name: String) {
    companion object {
        fun fromJSON(json: JSONObject): KrisKringleGroup {
            val error = "The server returned an invalid group."
            return KrisKringleGroup(json.requiredId("groupId", error), json.requiredString("name", error))
        }
    }
}

data class KrisKringleMember(
    val id: Int,
    val memberId: Int?,
    val groupId: Int,
    val name: String,
    val email: String?,
    val pictureVersion: String = "",
    val pictureURL: String? = null,
    val isCreator: Boolean = false,
    val children: List<Child> = emptyList(),
) {
    val displayName: String get() = name.ifEmpty { email ?: "Member" }
    val hasPicture: Boolean get() = pictureVersion.isNotEmpty() || pictureURL != null
    data class Child(val id: Int, val name: String) {
        companion object {
            fun fromJSON(json: JSONObject): Child {
                val error = "The server returned an invalid child member."
                return Child(json.requiredId("memberId", error), json.requiredString("name", error))
            }
        }
    }
    companion object {
        fun fromJSON(json: JSONObject): KrisKringleMember {
            val error = "The server returned an invalid group member."
            require(json.has("isCreator")) { error }
            val url = json.nullableString("pictureURL")?.takeIf {
                it.startsWith("https://", ignoreCase = true) || it.startsWith("http://", ignoreCase = true)
            }
            return KrisKringleMember(json.requiredId("userId", error),
                json.optInt("memberId").takeIf { it > 0 }, json.requiredId("groupId", error),
                json.requiredString("name", error), json.nullableString("email"),
                json.string("pictureVersion"), url, json.getBoolean("isCreator"),
                json.optJSONArray("children")?.objects()?.map(Child::fromJSON).orEmpty())
        }
    }
}

sealed class KrisKringleGiftGiverID {
    data class Member(val id: Int) : KrisKringleGiftGiverID()
    data class User(val id: Int) : KrisKringleGiftGiverID()
    val parameter: Pair<String, String> get() = when (this) {
        is Member -> "giverId" to id.toString()
        is User -> "giverUserId" to id.toString()
    }
}

data class KrisKringleGiftAssignment(val giverId: Int, val recipientName: String) {
    companion object {
        fun fromJSON(json: JSONObject): KrisKringleGiftAssignment = KrisKringleGiftAssignment(
            json.getInt("giverId"), json.requiredString("recipientName", "The server returned an invalid gift assignment."))
    }
}

data class KrisKringleGiftRecipient(val id: Int, val groupId: Int, val name: String) {
    companion object {
        fun fromJSON(json: JSONObject): KrisKringleGiftRecipient = KrisKringleGiftRecipient(
            json.getInt("memberId"), json.getInt("groupId"),
            json.requiredString("name", "The server returned an invalid gift recipient."))
    }
}

data class KrisKringleGiftOptions(
    val recipients: List<KrisKringleGiftRecipient>,
    val recipientId: Int?,
    val recipientName: String?,
) {
    companion object {
        fun fromJSON(json: JSONObject): KrisKringleGiftOptions = KrisKringleGiftOptions(
            (json.optJSONArray("options") ?: throw IllegalArgumentException("The server didn’t return gift recipient options."))
                .objects().map(KrisKringleGiftRecipient::fromJSON),
            if (json.isNull("recipientId")) null else json.getInt("recipientId"),
            json.nullableString("recipientName"))
    }
}

data class KrisKringleRoster(
    val groups: List<KrisKringleGroup>,
    val members: List<KrisKringleMember>,
    val assignments: Map<Int, KrisKringleGiftAssignment>,
) {
    companion object {
        fun fromJSON(json: JSONObject): KrisKringleRoster {
            val error = "The server didn’t return the Kris Kringle roster."
            val groups = json.optJSONArray("groups") ?: throw IllegalArgumentException(error)
            val members = json.optJSONArray("members") ?: throw IllegalArgumentException(error)
            val assignments = json.optJSONArray("assignments")?.objects()
                ?.map(KrisKringleGiftAssignment::fromJSON).orEmpty()
            return KrisKringleRoster(groups.objects().map(KrisKringleGroup::fromJSON),
                members.objects().map(KrisKringleMember::fromJSON),
                assignments.reversed().associateBy(KrisKringleGiftAssignment::giverId))
        }
    }
}

internal fun JSONArray.objects(): List<JSONObject> = (0 until length()).map { getJSONObject(it) }

data class KrisKringleServiceData(
    val groups: List<KrisKringleSummary> = emptyList(),
    val summary: KrisKringleSummary? = null,
    val roster: KrisKringleRoster? = null,
    val groupId: Int? = null,
    val giftOptions: KrisKringleGiftOptions? = null,
)
