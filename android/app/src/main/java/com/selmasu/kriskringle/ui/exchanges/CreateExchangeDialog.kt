package com.selmasu.kriskringle.ui.exchanges

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import com.selmasu.kriskringle.data.KrisKringleSummary
import com.selmasu.kriskringle.ui.components.PhotoPicker
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch

@Composable
internal fun CreateExchangeDialog(
    previousExchanges: List<KrisKringleSummary>,
    onDismiss: () -> Unit,
    onCreated: (KrisKringleSummary, ByteArray?) -> Unit
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var step by rememberSaveable { mutableStateOf(if (previousExchanges.isEmpty()) "form" else "prompt") }
    var copyFromId by rememberSaveable { mutableStateOf<Int?>(null) }
    var copyFromYear by rememberSaveable { mutableIntStateOf(0) }
    var copyFromName by rememberSaveable { mutableStateOf("") }
    var name by rememberSaveable { mutableStateOf("") }
    var firstGroup by rememberSaveable { mutableStateOf("") }
    var isSecret by rememberSaveable { mutableStateOf(true) }
    var picture by remember { mutableStateOf<ByteArray?>(null) }
    var busy by remember { mutableStateOf(false) }
    var photoPreparing by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    when (step) {
        "prompt" -> AlertDialog(
            onDismissRequest = onDismiss,
            title = { Text("Copy a previous year?") },
            text = { Text("You can copy groups, members and children from a previous Kris Kringle. Allocations won’t be copied.") },
            confirmButton = { TextButton(onClick = { step = "choose" }) { Text("Choose Year") } },
            dismissButton = { TextButton(onClick = { step = "form" }) { Text("Start Fresh") } }
        )
        "choose" -> AlertDialog(
            onDismissRequest = onDismiss,
            title = { Text("Copy from a previous year") },
            text = {
                LazyColumn(Modifier.fillMaxWidth().heightIn(max = 360.dp)) {
                    items(previousExchanges, key = { it.id }) { previous ->
                        TextButton(
                            onClick = {
                                if (name.isBlank() || name == copyFromName) name = previous.name
                                copyFromId = previous.id
                                copyFromYear = previous.year
                                copyFromName = previous.name
                                error = null
                                step = "form"
                            },
                            modifier = Modifier.fillMaxWidth()
                        ) { Text("${previous.year} · ${previous.name}", Modifier.fillMaxWidth()) }
                    }
                }
            },
            confirmButton = {},
            dismissButton = { TextButton(onClick = onDismiss) { Text("Cancel") } }
        )
        else -> ExchangeFormDialog(
            "Create Kris Kringle", busy, onDismiss, "Create",
            validName(name) && (copyFromId != null || validName(firstGroup)) && !photoPreparing,
            {
                busy = true
                error = null
                scope.launch {
                    try {
                        val summary = exchangeRequest(context) {
                            create(name.trim(), firstGroup.trim(), picture, copyFromId, isSecret)
                        }.summary ?: throw IllegalStateException("The server didn’t return the new Kris Kringle.")
                        onCreated(summary, picture)
                    } catch (cancelled: CancellationException) { throw cancelled }
                    catch (failure: Exception) { error = failure.message ?: "Couldn’t create the Kris Kringle. Please try again." }
                    finally { busy = false }
                }
            }
        ) {
            OutlinedTextField(name, { name = it }, label = { Text("Name") }, singleLine = true,
                enabled = !busy, supportingText = { Text("Up to 100 characters") }, modifier = Modifier.fillMaxWidth())
            if (copyFromId == null) {
                OutlinedTextField(firstGroup, { firstGroup = it }, label = { Text("First group") },
                    singleLine = true, enabled = !busy, supportingText = { Text("You’ll be part of this group.") },
                    modifier = Modifier.fillMaxWidth())
            } else {
                Text("Copying from $copyFromYear · $copyFromName", style = MaterialTheme.typography.titleSmall)
                Text("Groups, members and children will be copied. Allocations won’t be copied.")
                TextButton(onClick = { step = "choose" }, enabled = !busy && !photoPreparing) { Text("Change Year") }
            }
            SecretSetting(isSecret, { isSecret = it }, enabled = !busy)
            Text("Kris Kringle photo", style = MaterialTheme.typography.titleSmall)
            PhotoPicker(picture = picture, onPictureChange = { picture = it }, enabled = !busy,
                onPreparingChange = { photoPreparing = it })
            error?.let { ErrorText(it) }
        }
    }
}
