package com.selmasu.kriskringle.ui.exchanges

import android.app.Activity
import android.content.Intent
import android.provider.ContactsContract.CommonDataKinds.Email
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.ui.unit.dp
import com.selmasu.kriskringle.data.*
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch

@Composable
internal fun ExchangeFormDialog(
    title: String,
    busy: Boolean,
    onDismiss: () -> Unit,
    confirmLabel: String,
    canConfirm: Boolean,
    onConfirm: () -> Unit,
    content: @Composable ColumnScope.() -> Unit
) {
    AlertDialog(
        onDismissRequest = { if (!busy) onDismiss() },
        title = { Text(title) },
        text = {
            Column(
                Modifier.fillMaxWidth().heightIn(max = 500.dp).verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(14.dp)
            ) {
                content()
                if (busy) LoadingRow("Saving…")
            }
        },
        confirmButton = { TextButton(onClick = onConfirm, enabled = canConfirm && !busy) { Text(confirmLabel) } },
        dismissButton = { TextButton(onClick = onDismiss, enabled = !busy) { Text("Cancel") } }
    )
}

@Composable
internal fun AddMemberDialog(
    exchangeId: Int,
    initialRoster: KrisKringleRoster,
    onDismiss: () -> Unit,
    onUpdate: (KrisKringleRoster, Boolean) -> Unit
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var roster by remember { mutableStateOf(initialRoster) }
    var selectedGroupId by rememberSaveable { mutableStateOf<Int?>(null) }
    var choosingGroup by remember { mutableStateOf(false) }
    var name by rememberSaveable { mutableStateOf("") }
    var email by rememberSaveable { mutableStateOf("") }
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var showAddGroup by remember { mutableStateOf(false) }
    var newGroupName by rememberSaveable { mutableStateOf("") }
    var groupBusy by remember { mutableStateOf(false) }
    var groupError by remember { mutableStateOf<String?>(null) }
    val contactPicker = rememberLauncherForActivityResult(ActivityResultContracts.StartActivityForResult()) { result ->
        if (result.resultCode == Activity.RESULT_OK) {
            val uri = result.data?.data
            try {
                if (uri == null) throw IllegalStateException("No contact email was selected.")
                context.contentResolver.query(uri, arrayOf(Email.ADDRESS, Email.DISPLAY_NAME), null, null, null)?.use { cursor ->
                    if (!cursor.moveToFirst()) throw IllegalStateException("This contact doesn’t have an email address.")
                    val address = cursor.getString(cursor.getColumnIndexOrThrow(Email.ADDRESS)).orEmpty().trim()
                    if (address.isBlank()) throw IllegalStateException("This contact doesn’t have an email address.")
                    email = address
                    error = null
                    if (name.isBlank()) name = cursor.getString(cursor.getColumnIndexOrThrow(Email.DISPLAY_NAME)).orEmpty().trim().substringBefore(' ')
                } ?: throw IllegalStateException("Couldn’t read this contact’s email address.")
            } catch (failure: Exception) { error = failure.message ?: "Couldn’t read the selected contact." }
        }
    }
    ExchangeFormDialog("Add a Member", busy || groupBusy, onDismiss, "Add", selectedGroupId != null && validEmail(email) && validOptionalName(name), {
        busy = true
        error = null
        scope.launch {
            try {
                val updated = exchangeRequest(context) { addMember(exchangeId, selectedGroupId!!, email.trim(), name.trim()) }.roster
                    ?: throw IllegalStateException("The server didn’t return the roster.")
                onUpdate(updated, true)
            } catch (cancelled: CancellationException) { throw cancelled }
            catch (failure: Exception) { error = failure.message }
            finally { busy = false }
        }
    }) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.weight(1f)) {
                OutlinedButton(onClick = { choosingGroup = true }, enabled = !busy && !groupBusy) {
                    Text(roster.groups.firstOrNull { it.id == selectedGroupId }?.name ?: "Choose group")
                }
                DropdownMenu(choosingGroup, { choosingGroup = false }) {
                    roster.groups.forEach { group ->
                        DropdownMenuItem(text = { Text(group.name) }, onClick = { selectedGroupId = group.id; choosingGroup = false })
                    }
                }
            }
            TextButton(onClick = { showAddGroup = true }, enabled = !busy && !groupBusy) { Text("New group") }
        }
        OutlinedTextField(name, { name = it }, label = { Text("First name (optional)") }, singleLine = true, enabled = !busy, modifier = Modifier.fillMaxWidth())
        OutlinedTextField(email, { email = it }, label = { Text("Email address") }, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email), singleLine = true, enabled = !busy, modifier = Modifier.fillMaxWidth())
        TextButton(onClick = {
            try { contactPicker.launch(Intent(Intent.ACTION_PICK, Email.CONTENT_URI)) }
            catch (_: Exception) { error = "No contacts app is available. Enter their email address above." }
        }, enabled = !busy) { Text("Choose from Contacts") }
        Text("They’ll see this Kris Kringle when they sign in with this email address.", style = MaterialTheme.typography.bodySmall)
        error?.let { ErrorText(it) }
    }
    if (showAddGroup) ExchangeFormDialog("Add Group", groupBusy, { showAddGroup = false }, "Add", validName(newGroupName), {
        groupBusy = true
        groupError = null
        scope.launch {
            try {
                val result = exchangeRequest(context) { addGroup(exchangeId, newGroupName.trim()) }
                roster = result.roster ?: throw IllegalStateException("The server didn’t return the new group.")
                selectedGroupId = result.groupId
                onUpdate(roster, false)
                newGroupName = ""
                showAddGroup = false
            } catch (cancelled: CancellationException) { throw cancelled }
            catch (failure: Exception) { groupError = failure.message }
            finally { groupBusy = false }
        }
    }) {
        OutlinedTextField(newGroupName, { newGroupName = it }, label = { Text("Group name") }, singleLine = true, enabled = !groupBusy, modifier = Modifier.fillMaxWidth())
        Text("Enter a group name between 1 and 100 characters.", style = MaterialTheme.typography.bodySmall)
        groupError?.let { ErrorText(it) }
    }
}

@Composable
internal fun AddChildDialog(exchangeId: Int, parent: KrisKringleMember, onDismiss: () -> Unit, onAdded: (KrisKringleRoster) -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var name by rememberSaveable { mutableStateOf("") }
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    ExchangeFormDialog("Add Child", busy, onDismiss, "Add", validName(name), {
        busy = true
        error = null
        scope.launch {
            try {
                val roster = exchangeRequest(context) { addChild(exchangeId, parent.id, name.trim()) }.roster
                    ?: throw IllegalStateException("The server didn’t return the roster.")
                onAdded(roster)
            } catch (cancelled: CancellationException) { throw cancelled }
            catch (failure: Exception) { error = failure.message }
            finally { busy = false }
        }
    }) {
        Text("Parent: ${parent.displayName}")
        OutlinedTextField(name, { name = it }, label = { Text("Child’s name") }, singleLine = true, enabled = !busy, modifier = Modifier.fillMaxWidth())
        error?.let { ErrorText(it) }
    }
}

@Composable
internal fun EditMemberDialog(exchangeId: Int, member: EditableMember, onDismiss: () -> Unit, onUpdated: (KrisKringleRoster) -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var name by rememberSaveable(member.memberId, member.userId) { mutableStateOf(member.name) }
    var email by rememberSaveable(member.memberId, member.userId) { mutableStateOf(member.email.orEmpty()) }
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    val canSave = validOptionalName(name) && (if (member.isChild) name.isNotBlank() else validEmail(email)) &&
        (name.trim() != member.name || email.trim() != member.email.orEmpty())
    ExchangeFormDialog("Edit Member", busy, onDismiss, "Save", canSave, {
        busy = true
        error = null
        scope.launch {
            try {
                val roster = exchangeRequest(context) { updateMember(exchangeId, member.memberId, member.userId, name.trim(), email.trim()) }.roster
                    ?: throw IllegalStateException("The server didn’t return the roster.")
                onUpdated(roster)
            } catch (cancelled: CancellationException) { throw cancelled }
            catch (failure: Exception) { error = failure.message }
            finally { busy = false }
        }
    }) {
        OutlinedTextField(name, { name = it }, label = { Text(if (member.isChild) "Name" else "First name (optional)") }, singleLine = true, enabled = !busy, modifier = Modifier.fillMaxWidth())
        if (!member.isChild) {
            if (member.isCreator) {
                Text("Email address: $email")
                Text("The creator’s email is linked to their sign-in account.", style = MaterialTheme.typography.bodySmall)
            } else {
                OutlinedTextField(email, { email = it }, label = { Text("Email address") }, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email), singleLine = true, enabled = !busy, modifier = Modifier.fillMaxWidth())
                Text("This email determines who can sign in to view this member’s gifts and their children’s gifts.", style = MaterialTheme.typography.bodySmall)
            }
        }
        error?.let { ErrorText(it) }
    }
}

@Composable
internal fun GiftRecipientDialog(exchangeId: Int, giver: GiftGiver, groups: List<KrisKringleGroup>, onDismiss: () -> Unit, onUpdated: (KrisKringleRoster) -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var options by remember { mutableStateOf<KrisKringleGiftOptions?>(null) }
    var selectedId by rememberSaveable { mutableStateOf<Int?>(null) }
    var busy by remember { mutableStateOf(false) }
    var loading by remember { mutableStateOf(true) }
    var error by remember { mutableStateOf<String?>(null) }
    var refreshKey by remember { mutableIntStateOf(0) }
    var confirmClear by remember { mutableStateOf(false) }
    LaunchedEffect(refreshKey) {
        loading = true
        error = null
        options = null
        selectedId = null
        try { options = exchangeRequest(context) { giftRecipientOptions(exchangeId, giver.id) }.giftOptions ?: throw IllegalStateException("The server didn’t return gift recipient options.") }
        catch (cancelled: CancellationException) { throw cancelled }
        catch (failure: Exception) { error = failure.message }
        finally { loading = false }
    }
    fun save(recipientId: Int?, expectedRecipientId: Int?) {
        if (busy) return
        busy = true
        error = null
        scope.launch {
            try {
                val roster = exchangeRequest(context) { updateGiftAssignment(exchangeId, giver.id, recipientId, expectedRecipientId) }.roster
                    ?: throw IllegalStateException("The server didn’t return the roster.")
                onUpdated(roster)
            } catch (cancelled: CancellationException) { throw cancelled }
            catch (failure: Exception) { error = failure.message }
            finally { busy = false }
        }
    }
    ExchangeFormDialog("Gift Recipient", busy, onDismiss, "Save", selectedId != null && !loading && error == null, { save(selectedId, null) }) {
        Text("Buying a gift: ${giver.name}", style = MaterialTheme.typography.titleMedium)
        if (loading) LoadingRow("Loading recipients…")
        val currentOptions = options
        if (currentOptions != null) {
            if (currentOptions.recipientId != null) {
                Text("Recipient: ${currentOptions.recipientName.orEmpty()}")
                TextButton(onClick = { confirmClear = true }, enabled = !busy) { Text("Clear assignment") }
            } else if (currentOptions.recipients.isEmpty()) {
                Text("No valid recipients available. Members cannot buy for themselves or each other. Add another member or clear an existing assignment to open up choices.")
            } else {
                Text("Choose a recipient", style = MaterialTheme.typography.titleSmall)
                currentOptions.recipients.forEach { recipient ->
                    Row(
                        Modifier.fillMaxWidth().clickable(enabled = !busy) { selectedId = recipient.id }.padding(vertical = 6.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        RadioButton(selected = selectedId == recipient.id, onClick = { selectedId = recipient.id }, enabled = !busy)
                        Column(Modifier.weight(1f)) {
                            Text(recipient.name)
                            groups.firstOrNull { it.id == recipient.groupId }?.let { Text(it.name, style = MaterialTheme.typography.bodySmall) }
                        }
                    }
                }
            }
        }
        error?.let {
            ErrorText(it)
            TextButton(onClick = { refreshKey++ }, enabled = !busy) { Text("Refresh choices") }
        }
    }
    if (confirmClear) AlertDialog(
        onDismissRequest = { confirmClear = false },
        title = { Text("Clear this gift assignment?") },
        text = { Text("${giver.name} will no longer have a gift recipient.") },
        confirmButton = { TextButton(onClick = { confirmClear = false; save(null, options?.recipientId) }) { Text("Clear assignment") } },
        dismissButton = { TextButton(onClick = { confirmClear = false }) { Text("Cancel") } }
    )
}
