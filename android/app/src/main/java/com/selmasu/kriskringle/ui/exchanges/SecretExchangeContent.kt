package com.selmasu.kriskringle.ui.exchanges

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyListScope
import androidx.compose.foundation.selection.toggleable
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.unit.dp
import com.selmasu.kriskringle.data.KrisKringleSummary

@Composable
internal fun SecretSetting(
    isSecret: Boolean, onSecretChange: (Boolean) -> Unit, enabled: Boolean, requiresSave: Boolean = false
) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Row(
            Modifier.fillMaxWidth().toggleable(value = isSecret, enabled = enabled,
                role = Role.Switch, onValueChange = onSecretChange),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text("Secret", Modifier.weight(1f))
            Switch(checked = isSecret, onCheckedChange = null, enabled = enabled)
        }
        Text(
            "When secret, members only see who they and their children are buying for. Only you can see the full roster." +
                if (requiresSave) " Save to apply changes." else "",
            style = MaterialTheme.typography.bodySmall
        )
    }
}

internal fun LazyListScope.exchangeRosterItems(
    group: KrisKringleSummary, viewerUserId: Int, content: LazyListScope.() -> Unit
) {
    if (group.isSecret && group.createdBy != viewerUserId) {
        item(key = "secret-recipients") { SecretGiftRecipients(group) }
    } else {
        content()
    }
}

@Composable
internal fun SecretGiftRecipients(group: KrisKringleSummary) {
    Column(Modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        Text("Your Gift Recipients", style = MaterialTheme.typography.titleMedium)
        val recipient = group.recipientName?.takeIf { it.isNotBlank() }
        recipient?.let { GiftRecipientCard(null, it, group.name) }
        group.childGiftRecipients.forEach { child ->
            GiftRecipientCard(child.childName, child.recipientName, group.name)
        }
        if (recipient == null && group.childGiftRecipients.isEmpty()) {
            ExchangeCard { Text("Your gift recipients haven’t been assigned yet.") }
        }
    }
}
