package com.selmasu.kriskringle.ui.exchanges

import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp

@Composable
internal fun GiftRecipientCard(
    giverName: String?,
    recipientName: String,
    krisKringleName: String,
    onClick: (() -> Unit)? = null
) {
    val colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.primaryContainer.copy(alpha = .75f))
    val content: @Composable () -> Unit = {
        Column(Modifier.fillMaxWidth().padding(24.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Text("🎁", modifier = Modifier.fillMaxWidth(), textAlign = TextAlign.Center,
                style = MaterialTheme.typography.headlineLarge)
            Column(Modifier.semantics(mergeDescendants = true) {}, verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text(giverName?.let { "$it is buying for" } ?: "You’re buying for",
                    style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
                Text(recipientName, style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
            }
            Text(krisKringleName, style = MaterialTheme.typography.bodyMedium)
        }
    }
    if (onClick == null) {
        Card(colors = colors, modifier = Modifier.fillMaxWidth()) { content() }
    } else {
        Card(onClick = onClick, colors = colors, modifier = Modifier.fillMaxWidth()) { content() }
    }
}
