package com.selmasu.kriskringle.ui.auth

import android.app.Application
import android.os.SystemClock
import android.util.Patterns
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.lifecycle.AndroidViewModel
import com.selmasu.kriskringle.BuildConfig
import com.selmasu.kriskringle.data.Globals
import com.selmasu.kriskringle.data.UserServiceData
import com.selmasu.kriskringle.services.UserService
import com.selmasu.kriskringle.services.UserServiceListener

class AuthViewModel(application: Application) : AndroidViewModel(application), UserServiceListener {
    private var activeService: UserService? = null
    var restoring by mutableStateOf(true)
        private set
    var signedIn by mutableStateOf(false)
        private set
    var restoreError by mutableStateOf<String?>(null)
        private set
    var busy by mutableStateOf(false)
        private set
    var email by mutableStateOf("")
    var code by mutableStateOf("")
    var codeSent by mutableStateOf(false)
        private set
    var emailMode by mutableStateOf(false)
    var requestedEmail by mutableStateOf("")
        private set
    var resendAt by mutableLongStateOf(0L)
        private set
    var error by mutableStateOf<String?>(null)

    init {
        Globals.initialize(application)
        if (Globals.restoreSession() != null) restore() else restoring = false
    }

    private fun service(): UserService {
        activeService?.cancel()
        return UserService(this, getApplication()).also { activeService = it }
    }

    override fun onCleared() {
        activeService?.cancel()
        super.onCleared()
    }

    fun restore() {
        restoring = true
        restoreError = null
        service().getUser()
    }

    fun requestCode() {
        if (busy || (codeSent && SystemClock.elapsedRealtime() < resendAt)) return
        val address = (if (codeSent) requestedEmail else email).trim().lowercase()
        if (!Patterns.EMAIL_ADDRESS.matcher(address).matches()) {
            error = "Please enter a valid email address."
            return
        }
        email = address
        requestedEmail = address
        busy = true
        error = null
        service().sendOneTimeCode(address)
    }

    fun verifyCode() {
        if (busy || !codeSent || code.length != 6) return
        busy = true
        error = null
        service().loginOneTimeCode(requestedEmail, code)
    }

    fun useDifferentEmail() {
        if (busy) return
        codeSent = false
        code = ""
        error = null
    }

    fun exchangeGoogleToken(token: String) {
        if (busy) return
        busy = true
        error = null
        service().loginGoogle(token, BuildConfig.GOOGLE_WEB_CLIENT_ID)
    }

    fun signedOut() {
        activeService?.cancel()
        Globals.clearSession()
        signedIn = false
        restoring = false
        restoreError = null
        busy = false
        codeSent = false
        code = ""
        emailMode = false
    }

    override fun userServiceDone(type: UserService.Type, data: UserServiceData) {
        busy = false
        when (type) {
            UserService.Type.ONE_TIME_CODE_SENT -> {
                codeSent = true
                code = ""
                resendAt = SystemClock.elapsedRealtime() + data.retryAfter.coerceAtLeast(0) * 1000L
            }
            UserService.Type.SIGN_IN, UserService.Type.USER -> {
                restoring = false
                restoreError = null
                signedIn = true
                code = ""
            }
            else -> Unit
        }
    }

    override fun serviceError(message: String) {
        busy = false
        if (restoring) {
            restoring = false
            if (message.contains("sign in", ignoreCase = true) ||
                message.contains("not logged in", ignoreCase = true) ||
                message.contains("invalid token", ignoreCase = true)) {
                signedOut()
            } else restoreError = message
        } else {
            error = when (message) {
                "validationError", "unknownUserError" -> "Couldn’t sign in. Try again or continue with email."
                else -> message.ifBlank { "Please try again." }
            }
        }
    }
}
