package com.selmasu.kriskringle.ui.exchanges

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.selmasu.kriskringle.data.*
import com.selmasu.kriskringle.ui.components.PhotoPicker
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch

internal data class EditableMember(
    val memberId: Int?, val userId: Int, val name: String,
    val email: String?, val isCreator: Boolean
) {
    val isChild get() = userId == 0
}
internal data class GiftGiver(val id: KrisKringleGiftGiverID, val name: String) {
    val memberId get() = (id as? KrisKringleGiftGiverID.Member)?.id
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
internal fun ExchangeDetailScreen(
    initialGroup: KrisKringleSummary,
    photos: ExchangePhotoCache,
    onBack: () -> Unit,
    onUpdate: (KrisKringleSummary) -> Unit,
    viewerProfile: MyProfile? = null
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var group by remember(initialGroup.id) { mutableStateOf(initialGroup) }
    var name by rememberSaveable(initialGroup.id) { mutableStateOf(initialGroup.name) }
    var isSecret by rememberSaveable(initialGroup.id) { mutableStateOf(initialGroup.isSecret) }
    var roster by remember { mutableStateOf<KrisKringleRoster?>(null) }
    var loading by remember { mutableStateOf(true) }
    var busy by remember { mutableStateOf(false) }
    var photoPreparing by remember { mutableStateOf(false) }
    var picture by remember { mutableStateOf<ByteArray?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var refreshKey by remember { mutableIntStateOf(0) }
    var showMenu by remember { mutableStateOf(false) }
    var confirmClear by remember { mutableStateOf(false) }
    var addMember by remember { mutableStateOf(false) }
    var parentForChild by remember { mutableStateOf<KrisKringleMember?>(null) }
    var editingMember by remember { mutableStateOf<EditableMember?>(null) }
    var giftGiver by remember { mutableStateOf<GiftGiver?>(null) }
    val isCreator = group.createdBy == Globals.userId
    BackHandler { if (!busy && !photoPreparing) onBack() }
    LaunchedEffect(group.id, refreshKey) {
        loading = true
        error = null
        roster = null
        try {
            val result = exchangeRequest(context) { detail(group.id) }
            val updated = result.summary ?: throw IllegalStateException("The server didn’t return the Kris Kringle.")
            val updatedRoster = result.roster ?: throw IllegalStateException("The server didn’t return the Kris Kringle roster.")
            val nameEdited = name.trim() != group.name
            val secretEdited = isSecret != group.isSecret
            group = updated
            if (!nameEdited) name = updated.name
            if (!secretEdited) isSecret = updated.isSecret
            roster = updatedRoster
            onUpdate(updated)
        } catch (cancelled: CancellationException) { throw cancelled }
        catch (failure: Exception) { error = failure.message ?: "Couldn’t load the Kris Kringle." }
        finally { loading = false }
    }
    fun updateRecipients(clear: Boolean) {
        if (!isCreator || busy || roster == null) return
        busy = true
        error = null
        scope.launch {
            try { roster = exchangeRequest(context) { updateAllGiftRecipients(group.id, clear) }.roster ?: throw IllegalStateException("The server didn’t return the roster.") }
            catch (cancelled: CancellationException) { throw cancelled }
            catch (failure: Exception) { error = failure.message }
            finally { busy = false }
        }
    }
    Scaffold(
        containerColor = Color.Transparent,
        topBar = {
            TopAppBar(
                title = { Text(group.name, maxLines = 2) },
                navigationIcon = { TextButton(onClick = onBack, enabled = !busy && !photoPreparing) { Text("Back") } },
                actions = {
                    if (isCreator) Box {
                        TextButton(onClick = { showMenu = true }, enabled = !busy && !loading && roster != null) { Text("Gifts") }
                        DropdownMenu(showMenu, onDismissRequest = { showMenu = false }) {
                            DropdownMenuItem(text = { Text("Set Gift Recipients") }, onClick = { showMenu = false; updateRecipients(false) })
                            DropdownMenuItem(text = { Text("Clear Gift Recipients") }, onClick = { showMenu = false; confirmClear = true })
                        }
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.Transparent)
            )
        }
    ) { padding ->
        LazyColumn(
            Modifier.fillMaxSize().padding(padding),
            contentPadding = PaddingValues(20.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            item {
                ExchangePhoto(group, photos, Modifier.fillMaxWidth().height(210.dp), draft = picture, showError = true)
            }
            if (loading) item { LoadingRow("Loading Kris Kringle…") }
            if (busy) item { LoadingRow("Updating Kris Kringle…") }
            error?.let { message -> item { ErrorCard(message) { refreshKey++ } } }
            if (isCreator && roster != null) item {
                ExchangeCard {
                    Text("Kris Kringle Details", style = MaterialTheme.typography.titleMedium)
                    OutlinedTextField(name, { name = it }, label = { Text("Name") }, singleLine = true, enabled = !busy, modifier = Modifier.fillMaxWidth())
                    PhotoPicker(picture = picture, onPictureChange = { picture = it }, enabled = !busy, onPreparingChange = { photoPreparing = it })
                    SecretSetting(isSecret, { isSecret = it }, enabled = !busy, requiresSave = true)
                    Button(
                        enabled = !busy && !photoPreparing && validName(name) &&
                            (name.trim() != group.name || picture != null || isSecret != group.isSecret),
                        onClick = {
                            busy = true
                            error = null
                            val draft = picture
                            val updatedName = name.trim().takeIf { it != group.name }
                            val updatedSecret = isSecret.takeIf { it != group.isSecret }
                            scope.launch {
                                try {
                                    val updated = exchangeRequest(context) { update(group.id, updatedName, draft, updatedSecret) }.summary
                                        ?: throw IllegalStateException("The server didn’t return the updated Kris Kringle.")
                                    photos.store(updated, draft)
                                    group = updated
                                    name = updated.name
                                    isSecret = updated.isSecret
                                    picture = null
                                    onUpdate(updated)
                                } catch (cancelled: CancellationException) { throw cancelled }
                                catch (failure: Exception) { error = failure.message }
                                finally { busy = false }
                            }
                        }
                    ) { Text("Save changes") }
                }
            }
            if (isCreator && roster != null) item {
                Button(onClick = { addMember = true }, enabled = !busy && !photoPreparing, modifier = Modifier.fillMaxWidth()) { Text("Add member") }
            }
            val currentRoster = roster
            if (currentRoster != null) {
                exchangeRosterItems(group, Globals.userId) {
                    currentRoster.groups.forEach { memberGroup ->
                        item(key = "heading-${memberGroup.id}") {
                            Text(memberGroup.name, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 8.dp))
                        }
                        val groupMembers = currentRoster.members.filter { it.groupId == memberGroup.id }
                        if (groupMembers.isEmpty()) item(key = "empty-${memberGroup.id}") { ExchangeCard { Text("No members yet") } }
                        items(groupMembers, key = { "member-${it.id}" }) { member ->
                            ExchangeCard {
                                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                                    MemberAvatar(group.id, member,
                                        picture = viewerProfile?.picture.takeIf { member.id == Globals.userId })
                                    Column(Modifier.weight(1f)) {
                                        Text(
                                            member.displayName, style = MaterialTheme.typography.titleMedium,
                                            modifier = if (isCreator && !busy) Modifier.clickable { editingMember = EditableMember(member.memberId, member.id, member.name, member.email, member.isCreator) }
                                                .padding(vertical = 6.dp) else Modifier
                                        )
                                        if (member.isCreator) Text("Creator", style = MaterialTheme.typography.labelMedium)
                                    }
                                    if (isCreator) TextButton(
                                        onClick = { parentForChild = member }, enabled = !busy,
                                        modifier = Modifier.semantics { contentDescription = "Add child under ${member.displayName}" }
                                    ) { Text("Child +") }
                                }
                                val giver = GiftGiver(member.memberId?.let { KrisKringleGiftGiverID.Member(it) } ?: KrisKringleGiftGiverID.User(member.id), member.displayName)
                                GiftAssignmentRow(giver, currentRoster, isCreator, !busy) { giftGiver = giver }
                                member.children.forEach { child ->
                                    HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant.copy(alpha = .4f))
                                    Column(Modifier.padding(start = 24.dp)) {
                                        Text(
                                            "↳ ${child.name}", style = MaterialTheme.typography.titleMedium,
                                            modifier = if (isCreator && !busy) Modifier.clickable { editingMember = EditableMember(child.id, 0, child.name, null, false) }.padding(vertical = 8.dp) else Modifier
                                        )
                                        val childGiver = GiftGiver(KrisKringleGiftGiverID.Member(child.id), child.name)
                                        GiftAssignmentRow(childGiver, currentRoster, isCreator, !busy) { giftGiver = childGiver }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    if (confirmClear) AlertDialog(
        onDismissRequest = { confirmClear = false },
        title = { Text("Clear Gift Recipients?") },
        text = { Text("This removes every gift assignment from this Kris Kringle.") },
        confirmButton = { TextButton(onClick = { confirmClear = false; updateRecipients(true) }) { Text("Clear Gift Recipients") } },
        dismissButton = { TextButton(onClick = { confirmClear = false }) { Text("Cancel") } }
    )
    if (isCreator && addMember && roster != null) AddMemberDialog(group.id, roster!!, { addMember = false }, { updated, close -> roster = updated; if (close) addMember = false })
    if (isCreator && parentForChild != null) AddChildDialog(group.id, parentForChild!!, { parentForChild = null }, { roster = it; parentForChild = null })
    if (isCreator && editingMember != null) EditMemberDialog(group.id, editingMember!!, { editingMember = null }, { roster = it; editingMember = null })
    if (isCreator && giftGiver != null) GiftRecipientDialog(group.id, giftGiver!!, roster?.groups.orEmpty(), { giftGiver = null }, { roster = it; giftGiver = null })
}

@Composable
internal fun GiftAssignmentRow(giver: GiftGiver, roster: KrisKringleRoster, isCreator: Boolean, enabled: Boolean, onEdit: () -> Unit) {
    val assignment = giver.memberId?.let { roster.assignments[it] }
    if (isCreator) {
        TextButton(onClick = onEdit, enabled = enabled, contentPadding = PaddingValues(horizontal = 0.dp, vertical = 4.dp)) {
            Text(assignment?.let { "🎁 Buying for ${it.recipientName}" } ?: "🎁 Choose recipient")
        }
    } else if (assignment != null) {
        Text("🎁 Buying for ${assignment.recipientName}", style = MaterialTheme.typography.bodyMedium)
    }
}
