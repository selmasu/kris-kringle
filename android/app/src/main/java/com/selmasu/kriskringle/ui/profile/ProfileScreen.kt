package com.selmasu.kriskringle.ui.profile

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.unit.dp
import androidx.credentials.ClearCredentialStateRequest
import androidx.credentials.CredentialManager
import com.selmasu.kriskringle.data.Globals
import com.selmasu.kriskringle.data.MyProfile
import com.selmasu.kriskringle.data.UserServiceData
import com.selmasu.kriskringle.services.UserService
import com.selmasu.kriskringle.services.UserServiceListener
import com.selmasu.kriskringle.ui.components.PhotoPicker
import com.selmasu.kriskringle.ui.components.ProfileAvatar
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProfileScreen(onSignedOut: () -> Unit, onProfileChanged: () -> Unit = {}) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val signedOut by rememberUpdatedState(onSignedOut)
    val profileChanged by rememberUpdatedState(onProfileChanged)
    val services = remember { mutableListOf<UserService>() }
    var attached by remember { mutableStateOf(true) }
    var profile by remember { mutableStateOf<MyProfile?>(null) }
    var name by remember { mutableStateOf("") }
    var selectedPhoto by remember { mutableStateOf<ByteArray?>(null) }
    var preparingPhoto by remember { mutableStateOf(false) }
    var loading by remember { mutableStateOf(true) }
    var saving by remember { mutableStateOf(false) }
    var signingOut by remember { mutableStateOf(false) }
    var deleting by remember { mutableStateOf(false) }
    var confirmDelete by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var success by remember { mutableStateOf<String?>(null) }
    val sessionToken = remember { Globals.token }
    val busy = loading || saving || signingOut || deleting

    DisposableEffect(Unit) {
        onDispose {
            attached = false
            services.forEach { it.cancel() }
        }
    }

    fun request(
        action: (UserService) -> Unit,
        onSuccess: (UserService.Type, UserServiceData) -> Unit,
        onError: (String) -> Unit
    ) {
        val service = UserService(object : UserServiceListener {
            override fun userServiceDone(type: UserService.Type, data: UserServiceData) {
                if (attached) onSuccess(type, data)
            }
            override fun serviceError(message: String) {
                if (attached) onError(message)
            }
        }, context)
        services.add(service)
        action(service)
    }

    fun load() {
        loading = true
        error = null
        request({ it.getMyProfile() }, { _, data ->
            loading = false
            if (Globals.token == sessionToken) {
                val loaded = data.profile
                if (loaded == null) {
                    error = "The server didn’t return your profile. Please try again."
                } else {
                    profile = loaded
                    name = loaded.name
                    selectedPhoto = null
                }
            }
        }, { message -> loading = false; error = message })
    }

    fun finishSignOut() {
        scope.launch {
            try {
                CredentialManager.create(context).clearCredentialState(ClearCredentialStateRequest())
            } catch (cancelled: CancellationException) {
                throw cancelled
            } catch (_: Exception) {
                // The server session is already closed; credential-provider cleanup is best effort.
            } finally {
                // Navigation or rotation must not keep the app signed in after server logout succeeds.
                signedOut()
            }
        }
    }

    LaunchedEffect(Unit) { load() }

    val trimmedName = name.trim()
    val nameChanged = profile?.let { trimmedName != it.name } ?: false
    val validName = trimmedName.codePointCount(0, trimmedName.length) in 1..100 &&
        trimmedName.none { Character.isISOControl(it) }
    val canSave = profile != null && !busy && !preparingPhoto &&
        (!nameChanged || validName) && (nameChanged || selectedPhoto != null)

    Scaffold(
        containerColor = Color.Transparent,
        topBar = { TopAppBar(title = { Text("Me") }, colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.Transparent)) }
    ) { padding ->
        Column(
            Modifier.fillMaxSize().padding(padding).verticalScroll(rememberScrollState()).padding(20.dp),
            verticalArrangement = Arrangement.spacedBy(20.dp)
        ) {
            if (loading) {
                CircularProgressIndicator(modifier = Modifier.align(Alignment.CenterHorizontally))
                Text("Loading profile…", modifier = Modifier.align(Alignment.CenterHorizontally))
            }
            profile?.let { loaded ->
                Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface.copy(alpha = 0.72f))) {
                    Column(Modifier.fillMaxWidth().padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        Text("Profile", style = MaterialTheme.typography.titleMedium)
                        if (selectedPhoto == null) {
                            ProfileAvatar(loaded.picture, loaded.name, Modifier.align(Alignment.CenterHorizontally),
                                pictureURL = Globals.user?.picture)
                        }
                        PhotoPicker(selectedPhoto, { selectedPhoto = it; success = null }, enabled = !busy,
                            onPreparingChange = { preparingPhoto = it })
                        OutlinedTextField(
                            value = name, onValueChange = { name = it; success = null }, label = { Text("Display name") },
                            modifier = Modifier.fillMaxWidth(), enabled = !busy, singleLine = true,
                            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Words),
                            isError = nameChanged && !validName,
                            supportingText = {
                                if (nameChanged && !validName) Text("Enter a name between 1 and 100 characters.")
                            }
                        )
                        Button(
                            onClick = {
                                val updatedName = trimmedName.takeIf { nameChanged }
                                val picture = selectedPhoto
                                saving = true
                                error = null
                                success = null
                                request({ it.updateMyProfile(updatedName, picture) }, { _, _ ->
                                    saving = false
                                    if (Globals.token == sessionToken) {
                                        profile = MyProfile(updatedName ?: loaded.name, picture ?: loaded.picture)
                                        selectedPhoto = null
                                        success = "Profile saved"
                                        profileChanged()
                                    }
                                }, { message -> saving = false; error = message })
                            }, enabled = canSave, modifier = Modifier.fillMaxWidth()
                        ) { Text(if (saving) "Saving…" else "Save Profile") }
                        success?.let { Text(it, color = MaterialTheme.colorScheme.primary) }
                    }
                }
            }
            error?.let { message ->
                Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface.copy(alpha = 0.85f))) {
                    Column(Modifier.fillMaxWidth().padding(16.dp)) {
                        Text(message, color = MaterialTheme.colorScheme.error)
                        if (profile == null && !loading) TextButton(onClick = { load() }) { Text("Try Again") }
                    }
                }
            }
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface.copy(alpha = 0.72f))) {
                Column(Modifier.fillMaxWidth().padding(12.dp)) {
                    TextButton(
                        onClick = {
                            signingOut = true
                            error = null
                            request({ it.logout() }, { _, _ -> finishSignOut() },
                                { message -> signingOut = false; error = message })
                        }, enabled = !busy && !preparingPhoto, modifier = Modifier.fillMaxWidth()
                    ) { Text(if (signingOut) "Logging Out…" else "Log Out", color = MaterialTheme.colorScheme.error) }
                    TextButton(
                        onClick = { confirmDelete = true }, enabled = !busy && !preparingPhoto,
                        modifier = Modifier.fillMaxWidth()
                    ) { Text(if (deleting) "Deleting Account…" else "Delete Account", color = MaterialTheme.colorScheme.error) }
                }
            }
            Spacer(Modifier.height(12.dp))
        }
    }
    if (confirmDelete) {
        AlertDialog(
            onDismissRequest = { confirmDelete = false }, title = { Text("Delete your account?") },
            text = { Text("This permanently deletes your Kris Kringle account. This action cannot be undone.") },
            confirmButton = {
                TextButton(onClick = {
                    confirmDelete = false
                    deleting = true
                    error = null
                    request({ it.deleteAccount() }, { _, _ -> finishSignOut() },
                        { message -> deleting = false; error = message })
                }) { Text("Delete Account", color = MaterialTheme.colorScheme.error) }
            }, dismissButton = { TextButton(onClick = { confirmDelete = false }) { Text("Cancel") } }
        )
    }
}

