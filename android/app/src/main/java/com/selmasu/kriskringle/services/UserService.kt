package com.selmasu.kriskringle.services

import android.content.Context
import android.util.Base64
import com.selmasu.kriskringle.data.*
import org.json.JSONObject

/** Account operations required by Kris Kringle, with the original listener/service pattern. */
class UserService(private val listener: UserServiceListener, private val context: Context) {
    enum class Type { SIGN_IN, LOGOUT, USER, ONE_TIME_CODE_SENT, PROFILE, UPDATE, DELETE_ACCOUNT }
    private val connections = mutableListOf<RemoteConnection>()
    private val profileRequests = mutableListOf<KrisKringleService>()

    fun loginGoogle(idToken: String, clientId: String) {
        if (idToken.isBlank() || clientId.isBlank()) {
            listener.serviceError("Google sign-in is not configured. Please continue with email.")
            return
        }
        login("loginGoogle", mapOf("idToken" to idToken, "clientId" to clientId))
    }

    fun sendOneTimeCode(email: String) = request("users", "users", "sendOneTimeCode",
        mapOf("email" to email.trim()), authenticated = false) {
        if (!it.optBoolean("sent")) {
            listener.serviceError("Unable to send a code. Please try again.")
        } else {
            listener.userServiceDone(Type.ONE_TIME_CODE_SENT,
                UserServiceData(json = it, retryAfter = it.optInt("retryAfter", 60).coerceAtLeast(0)))
        }
    }

    fun loginOneTimeCode(email: String, code: String) = login("loginOneTimeCode",
        mapOf("email" to email.trim(), "oneTimeCode" to code.trim()))

    fun getUser() = request("users", "users", "getUser") { json ->
        require(json.optInt("userId") > 0) { "Please sign in again." }
        val existing = Globals.user ?: throw IllegalArgumentException("Please sign in again.")
        val user = User.fromJSON(json, existing.token)
        require(user.userId == existing.userId) { "Please sign in again." }
        Globals.saveUser(user.copy(email = user.email.ifEmpty { existing.email }))
        listener.userServiceDone(Type.USER, UserServiceData(json))
    }

    fun logout() = request("framework", "authentication", "logout", allowNull = true) {
        require(it.optBoolean("_nullResponse") || it.optBoolean("loggedOut")) {
            "Unable to log out. Please try again."
        }
        Globals.clearSession()
        listener.userServiceDone(Type.LOGOUT, UserServiceData(it))
    }

    fun deleteAccount() = request("users", "users", "deleteMyAccount", allowNull = true) {
        requireAccountDeletionResponse(it)
        Globals.clearSession()
        listener.userServiceDone(Type.DELETE_ACCOUNT, UserServiceData(it))
    }

    fun getMyProfile() {
        val sessionToken = Globals.token
        request("users", "users", "getMyProfile") { json ->
            val picture = json.nullableString("picture")?.takeIf(String::isNotEmpty)?.let {
                Base64.decode(it, Base64.DEFAULT)
            }
            val profile = MyProfile.fromJSON(json, picture)
            if (profile.name.isNotBlank()) {
                listener.userServiceDone(Type.PROFILE, UserServiceData(json = json, profile = profile))
            } else {
                loadProfileName(profile, json, sessionToken)
            }
        }
    }

    fun updateMyProfile(name: String?, picture: ByteArray?) = request("users", "users", "updateMyProfile",
        name?.let { mapOf("name" to it.trim()) } ?: emptyMap(), picture = picture) {
        require(it.optBoolean("saved")) { "The server couldn’t save your profile. Please try again." }
        if (name != null) Globals.user?.let { user -> Globals.saveUser(user.copy(name = name.trim())) }
        listener.userServiceDone(Type.UPDATE, UserServiceData(it))
    }

    fun cancel() {
        connections.forEach(RemoteConnection::cancel)
        connections.clear()
        profileRequests.forEach(KrisKringleService::cancel)
        profileRequests.clear()
    }

    private fun login(function: String, fields: Map<String, String>) =
        request("framework", "authentication", function, fields, authenticated = false) {
            val token = it.string("token")
            require(token.isNotBlank()) { "Unable to sign in. Please try again." }
            val user = User.fromJSON(it.getJSONObject("user"), token)
            Globals.saveUser(user)
            listener.userServiceDone(Type.SIGN_IN, UserServiceData(it))
        }

    private fun request(app: String, service: String, function: String,
                        fields: Map<String, String> = emptyMap(), picture: ByteArray? = null,
                        authenticated: Boolean = true, allowNull: Boolean = false,
                        complete: (JSONObject) -> Unit) {
        RemoteConnection(object : RemoteConnectionListener {
            override fun objectRequestComplete(data: JSONObject) {
                try { complete(data) } catch (error: Exception) {
                    listener.serviceError(error.message ?: "The server returned an unexpected response. Please try again.")
                }
            }
            override fun remoteConnectionError(message: String) = listener.serviceError(message)
        }, context).also(connections::add).requestObject(app, service, function, fields, picture,
            authenticated, allowNull)
    }

    // Older accounts can have a roster name before their account profile has one.
    private fun loadProfileName(profile: MyProfile, json: JSONObject, sessionToken: String) {
        val listService = KrisKringleService(object : KrisKringleServiceListener {
            override fun krisKringleServiceDone(type: KrisKringleService.Type, data: KrisKringleServiceData) {
                val groups = data.groups.sortedWith(compareByDescending<KrisKringleSummary> { it.year }.thenByDescending { it.id })
                loadProfileNameFromGroup(groups, 0, profile, json, sessionToken)
            }
            override fun serviceError(message: String) = listener.serviceError(message)
        }, context)
        profileRequests.add(listService)
        listService.list()
    }

    private fun loadProfileNameFromGroup(groups: List<KrisKringleSummary>, index: Int, profile: MyProfile,
                                         json: JSONObject, sessionToken: String) {
        if (sessionToken != Globals.token) {
            listener.serviceError("Your session changed. Please try again.")
            return
        }
        if (index >= groups.size) {
            listener.userServiceDone(Type.PROFILE, UserServiceData(json = json, profile = profile))
            return
        }
        val service = KrisKringleService(object : KrisKringleServiceListener {
            override fun krisKringleServiceDone(type: KrisKringleService.Type, data: KrisKringleServiceData) {
                val name = data.roster?.members?.firstOrNull { it.id == Globals.userId }?.name?.trim().orEmpty()
                if (name.isNotBlank()) {
                    listener.userServiceDone(Type.PROFILE, UserServiceData(json = json, profile = profile.copy(name = name)))
                } else {
                    loadProfileNameFromGroup(groups, index + 1, profile, json, sessionToken)
                }
            }
            override fun serviceError(message: String) = listener.serviceError(message)
        }, context)
        profileRequests.add(service)
        service.detail(groups[index].id)
    }
}

/** deleteMyAccount is a void PHP endpoint; an object is not its success response. */
internal fun requireAccountDeletionResponse(response: JSONObject) {
    require(response.optBoolean("_nullResponse")) { "Unable to delete your account. Please try again." }
}
