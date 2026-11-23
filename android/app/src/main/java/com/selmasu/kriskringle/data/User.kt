package com.selmasu.kriskringle.data

import org.json.JSONObject

data class User(
    val userId: Int,
    val firstName: String = "",
    val lastName: String = "",
    val name: String = "",
    val email: String = "",
    val picture: String = "",
    val token: String = "",
) {
    fun toJSON(): JSONObject = JSONObject().put("userId", userId).put("firstName", firstName)
        .put("lastName", lastName).put("displayName", name).put("email", email)
        .put("picture", picture).put("token", token)

    companion object {
        fun fromJSON(json: JSONObject, token: String = json.string("token")): User {
            val id = json.requiredId("userId", "The server returned an invalid user.")
            val firstName = json.string("firstName")
            val name = listOf(json.string("displayName"), json.string("name"), firstName)
                .map(String::trim).firstOrNull(String::isNotEmpty).orEmpty()
            return User(id, firstName, json.string("lastName"), name,
                json.string("email"), json.string("picture"), token)
        }
    }
}

data class MyProfile(
    val name: String,
    val picture: ByteArray? = null,
) {
    companion object {
        fun fromJSON(json: JSONObject, picture: ByteArray? = null): MyProfile = MyProfile(
            name = json.requiredString("name", "The server returned an invalid profile."),
            picture = picture,
        )
    }
}

internal fun JSONObject.string(key: String): String = if (isNull(key)) "" else optString(key, "")
internal fun JSONObject.nullableString(key: String): String? = if (isNull(key)) null else optString(key)
internal fun JSONObject.requiredId(key: String, message: String): Int =
    optInt(key, 0).takeIf { it > 0 } ?: throw IllegalArgumentException(message)
internal fun JSONObject.requiredString(key: String, message: String): String =
    (opt(key) as? String) ?: throw IllegalArgumentException(message)
