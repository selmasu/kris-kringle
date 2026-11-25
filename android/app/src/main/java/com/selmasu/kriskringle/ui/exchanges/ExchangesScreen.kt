package com.selmasu.kriskringle.ui.exchanges

import android.content.Context
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.selmasu.kriskringle.data.*
import com.selmasu.kriskringle.services.*
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.suspendCancellableCoroutine
import java.util.Calendar
import kotlin.coroutines.resume
import kotlin.coroutines.resumeWithException

internal suspend fun exchangeRequest(
    context: Context,
    action: KrisKringleService.() -> Unit
): KrisKringleServiceData = suspendCancellableCoroutine { continuation ->
    val service = KrisKringleService(object : KrisKringleServiceListener {
        override fun krisKringleServiceDone(type: KrisKringleService.Type, data: KrisKringleServiceData) {
            if (continuation.isActive) continuation.resume(data)
        }
        override fun serviceError(message: String) {
            if (continuation.isActive) continuation.resumeWithException(IllegalStateException(message))
        }
    }, context.applicationContext)
    continuation.invokeOnCancellation { service.cancel() }
    try { service.action() } catch (error: Exception) {
        if (continuation.isActive) continuation.resumeWithException(error)
    }
}

internal val LocalOnSessionExpired = staticCompositionLocalOf<() -> Unit> { {} }

internal fun validName(value: String): Boolean = value.trim().let { it.codePointCount(0, it.length) in 1..100 }
internal fun validOptionalName(value: String): Boolean = value.trim().let { it.codePointCount(0, it.length) <= 100 }
internal fun validEmail(value: String): Boolean = android.util.Patterns.EMAIL_ADDRESS.matcher(value.trim()).matches()

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ExchangesScreen(onSessionExpired: () -> Unit = {}, viewerProfile: MyProfile? = null) {
    val context = LocalContext.current
    var groups by remember { mutableStateOf<List<KrisKringleSummary>>(emptyList()) }
    var loading by remember { mutableStateOf(true) }
    var error by remember { mutableStateOf<String?>(null) }
    var selectedId by rememberSaveable { mutableStateOf<Int?>(null) }
    var creating by rememberSaveable { mutableStateOf(false) }
    var refreshKey by remember { mutableIntStateOf(0) }
    val photos = remember { ExchangePhotoCache() }
    fun upsert(group: KrisKringleSummary) {
        groups = (groups.filterNot { it.id == group.id } + group)
            .sortedWith(compareByDescending<KrisKringleSummary> { it.year }.thenByDescending { it.id })
    }
    LaunchedEffect(refreshKey) {
        loading = true
        error = null
        try {
            groups = exchangeRequest(context) { list() }.groups
                .sortedWith(compareByDescending<KrisKringleSummary> { it.year }.thenByDescending { it.id })
        } catch (cancelled: CancellationException) { throw cancelled }
        catch (failure: Exception) { error = failure.message ?: "Couldn’t load Kris Kringles. Please try again." }
        finally { loading = false }
    }
    val selected = groups.firstOrNull { it.id == selectedId }
    CompositionLocalProvider(LocalOnSessionExpired provides onSessionExpired) {
        if (selected != null) {
            ExchangeDetailScreen(
                initialGroup = selected,
                photos = photos,
                onBack = { selectedId = null; refreshKey++ },
                onUpdate = ::upsert,
                viewerProfile = viewerProfile
            )
        } else {
            Scaffold(
                containerColor = androidx.compose.ui.graphics.Color.Transparent,
                topBar = {
                    TopAppBar(title = { Text("Kris Kringles") }, actions = {
                        TextButton(onClick = { refreshKey++ }, enabled = !loading) { Text("Refresh") }
                        TextButton(onClick = { creating = true }, enabled = !loading || groups.isNotEmpty()) { Text("Create") }
                    }, colors = TopAppBarDefaults.topAppBarColors(containerColor = androidx.compose.ui.graphics.Color.Transparent))
                }
            ) { padding ->
                LazyColumn(
                    modifier = Modifier.fillMaxSize().padding(padding),
                    contentPadding = PaddingValues(20.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    if (loading) item { LoadingRow("Loading Kris Kringles…") }
                    error?.let { message -> item { ErrorCard(message, onRetry = { refreshKey++ }) } }
                    if (!loading && error == null && groups.isEmpty()) item {
                        ExchangeCard {
                            Text("🎁", style = MaterialTheme.typography.displayMedium)
                            Text("No Kris Kringles Yet", style = MaterialTheme.typography.headlineSmall)
                            Text("You haven’t created a Kris Kringle or been invited to one yet.")
                            Button(onClick = { creating = true }) { Text("Create Kris Kringle") }
                        }
                    }
                    val year = Calendar.getInstance().get(Calendar.YEAR)
                    groups.filter { it.year == year }.forEach { group ->
                        group.recipientName?.takeIf { it.isNotBlank() }?.let { recipientName ->
                            item(key = "highlight-${group.id}") {
                                GiftRecipientCard("You’re buying for $recipientName", group.name) { selectedId = group.id }
                            }
                        }
                        group.childGiftRecipients.forEach { child ->
                            item(key = "highlight-${group.id}-child-${child.id}") {
                                GiftRecipientCard("${child.childName} is buying for ${child.recipientName}", group.name) { selectedId = group.id }
                            }
                        }
                    }
                    items(groups, key = { "exchange-${it.id}" }) { group ->
                        Card(
                            onClick = { selectedId = group.id },
                            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface.copy(alpha = .75f)),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                                ExchangePhoto(group, photos, Modifier.size(54.dp))
                                Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(5.dp)) {
                                    Text(group.name, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                                    Text("${group.year} · ${if (group.createdBy == Globals.userId) "Created by you" else "Invited"}", style = MaterialTheme.typography.bodyMedium)
                                }
                                Text("›", style = MaterialTheme.typography.headlineSmall)
                            }
                        }
                    }
                }
            }
        }
        if (creating) {
            CreateExchangeDialog(
                previousExchanges = previousExchangesForCopy(groups, Calendar.getInstance().get(Calendar.YEAR)),
                onDismiss = { creating = false },
                onCreated = { group, picture ->
                    photos.store(group, picture)
                    upsert(group)
                    creating = false
                    selectedId = group.id
                }
            )
        }
    }
}

@Composable
private fun GiftRecipientCard(assignment: String, krisKringleName: String, onClick: () -> Unit) {
    Card(
        onClick = onClick,
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.primaryContainer.copy(alpha = .75f)),
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(Modifier.fillMaxWidth().padding(24.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Text("🎁", modifier = Modifier.fillMaxWidth(), textAlign = TextAlign.Center,
                style = MaterialTheme.typography.headlineLarge)
            Text(assignment, style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
            Text(krisKringleName, style = MaterialTheme.typography.bodyMedium)
        }
    }
}

@Composable
internal fun ExchangeCard(content: @Composable ColumnScope.() -> Unit) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface.copy(alpha = .78f)),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(Modifier.padding(16.dp).fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(12.dp), content = content)
    }
}

@Composable
internal fun LoadingRow(message: String) {
    Row(Modifier.fillMaxWidth().padding(16.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(14.dp)) {
        CircularProgressIndicator(Modifier.size(22.dp), strokeWidth = 2.dp)
        Text(message)
    }
}

@Composable
internal fun ErrorText(message: String) {
    val onSessionExpired = LocalOnSessionExpired.current
    LaunchedEffect(message) {
        if (message == "Please sign in again.") onSessionExpired()
    }
    Text(message, color = MaterialTheme.colorScheme.error)
}

@Composable
internal fun ErrorCard(message: String, onRetry: () -> Unit) {
    ExchangeCard {
        ErrorText(message)
        TextButton(onClick = onRetry) { Text("Try Again") }
    }
}
