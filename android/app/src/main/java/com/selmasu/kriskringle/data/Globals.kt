package com.selmasu.kriskringle.data

import android.content.Context
import android.content.SharedPreferences
import org.json.JSONObject

/** The last signed-in account; passwords and Google credentials are never persisted. */
object Globals {
    private var preferences: SharedPreferences? = null
    var user: User? = null
        private set
    val token: String get() = user?.token.orEmpty()
    val userId: Int get() = user?.userId ?: 0

    fun initialize(context: Context) {
        if (preferences == null) {
            preferences = context.applicationContext.getSharedPreferences("session", Context.MODE_PRIVATE)
            restoreSession()
        }
    }

    fun restoreSession(): User? {
        user = runCatching {
            preferences?.getString("user", null)?.let { User.fromJSON(JSONObject(it)) }
                ?.takeIf { it.token.isNotBlank() }
        }.getOrNull()
        return user
    }

    fun saveUser(value: User) {
        user = value
        preferences?.edit()?.putString("user", value.toJSON().toString())?.apply()
    }

    fun clearSession() {
        user = null
        preferences?.edit()?.clear()?.apply()
    }
}
