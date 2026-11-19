package com.selmasu.kriskringle.ui.auth

import android.os.SystemClock
import androidx.activity.compose.BackHandler
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalSoftwareKeyboardController
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.selmasu.kriskringle.R
import com.selmasu.kriskringle.ui.theme.ChristmasGreen
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

@Composable
fun LoginScreen(model: AuthViewModel) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val keyboard = LocalSoftwareKeyboardController.current
    var googleBusy by remember { mutableStateOf(false) }
    val busy = model.busy || googleBusy
    var now by remember { mutableLongStateOf(SystemClock.elapsedRealtime()) }
    LaunchedEffect(model.resendAt) {
        while (true) { now = SystemClock.elapsedRealtime(); delay(1000) }
    }
    val seconds = ((model.resendAt - now + 999) / 1000).coerceAtLeast(0)
    BackHandler(model.emailMode && !busy) {
        model.useDifferentEmail()
        model.emailMode = false
    }
    BoxWithConstraints(Modifier.fillMaxSize().safeDrawingPadding().imePadding()) {
    Column(
        modifier = Modifier.fillMaxWidth().verticalScroll(rememberScrollState())
            .heightIn(min = maxHeight).padding(horizontal = 24.dp, vertical = 32.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Bottom
    ) {
        Column(
            modifier = Modifier.widthIn(max = 380.dp)
                .background(Color.Black.copy(alpha = .68f), RoundedCornerShape(24.dp))
                .padding(24.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            if (model.emailMode) {
                Text(if (model.codeSent) "Check your email" else "Sign in or create an account",
                    style = MaterialTheme.typography.titleLarge, textAlign = TextAlign.Center)
                Text(
                    if (model.codeSent) "Enter the six-digit code sent to ${model.requestedEmail}. It expires in five minutes."
                    else "We’ll email you a one-time code. No password needed.",
                    textAlign = TextAlign.Center, style = MaterialTheme.typography.bodyMedium
                )
                if (model.codeSent) {
                    OutlinedTextField(
                        value = model.code,
                        onValueChange = { model.code = it.filter { c -> c in '0'..'9' }.take(6) },
                        label = { Text("6-digit code") }, singleLine = true, enabled = !busy,
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword, imeAction = ImeAction.Go),
                        keyboardActions = KeyboardActions(onGo = { keyboard?.hide(); model.verifyCode() }),
                        modifier = Modifier.fillMaxWidth()
                    )
                } else {
                    OutlinedTextField(
                        value = model.email, onValueChange = { model.email = it },
                        label = { Text("Email address") }, singleLine = true, enabled = !busy,
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email, imeAction = ImeAction.Go),
                        keyboardActions = KeyboardActions(onGo = { keyboard?.hide(); model.requestCode() }),
                        modifier = Modifier.fillMaxWidth()
                    )
                }
                Button(
                    onClick = { keyboard?.hide(); if (model.codeSent) model.verifyCode() else model.requestCode() },
                    enabled = !busy && if (model.codeSent) model.code.length == 6 else model.email.isNotBlank(),
                    modifier = Modifier.fillMaxWidth().heightIn(min = 48.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = ChristmasGreen, contentColor = Color.White)
                ) {
                    if (busy) { CircularProgressIndicator(Modifier.size(18.dp), strokeWidth = 2.dp); Spacer(Modifier.width(8.dp)) }
                    Text(if (model.codeSent) "Verify and Continue" else "Send Code")
                }
                if (model.codeSent) {
                    TextButton(onClick = model::requestCode, enabled = !busy && seconds == 0L) {
                        Text(if (seconds > 0) "Resend code in ${seconds}s" else "Resend code")
                    }
                    TextButton(onClick = model::useDifferentEmail, enabled = !busy) { Text("Use a different email") }
                }
                TextButton(onClick = { model.useDifferentEmail(); model.emailMode = false }, enabled = !busy) { Text("Cancel") }
            } else {
                Button(
                    onClick = { model.emailMode = true }, enabled = !busy,
                    modifier = Modifier.fillMaxWidth().heightIn(min = 48.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = Color.White, contentColor = Color.Black)
                ) { Text("Continue with Email") }
                Text("New here or invited by email?\nContinue with Email.", textAlign = TextAlign.Center,
                    style = MaterialTheme.typography.bodySmall)
                Button(
                    onClick = {
                        googleBusy = true
                        model.error = null
                        scope.launch {
                            try {
                                model.exchangeGoogleToken(googleIdToken(context))
                            } catch (e: CancellationException) { throw e
                            } catch (e: Exception) {
                                model.error = googleSignInErrorMessage(e)
                            } finally { googleBusy = false }
                        }
                    }, enabled = !busy,
                    modifier = Modifier.fillMaxWidth().heightIn(min = 48.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = Color.White, contentColor = Color.Black)
                ) {
                    if (busy) CircularProgressIndicator(Modifier.size(20.dp), color = ChristmasGreen, strokeWidth = 2.dp)
                    else Image(painterResource(R.drawable.google_logo), contentDescription = null, modifier = Modifier.size(20.dp))
                    Spacer(Modifier.width(12.dp))
                    Text("Sign in with Google")
                }
            }
            model.error?.let { message ->
                Text(message, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodyMedium)
            }
        }
    }
    }
}
