package com.selmasu.kriskringle

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.SystemBarStyle
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.selmasu.kriskringle.data.Globals
import com.selmasu.kriskringle.data.MyProfile
import com.selmasu.kriskringle.data.UserServiceData
import com.selmasu.kriskringle.services.UserService
import com.selmasu.kriskringle.services.UserServiceListener
import com.selmasu.kriskringle.ui.auth.AuthViewModel
import com.selmasu.kriskringle.ui.auth.LoginScreen
import com.selmasu.kriskringle.ui.components.ProfileAvatar
import com.selmasu.kriskringle.ui.exchanges.ExchangesScreen
import com.selmasu.kriskringle.ui.profile.ProfileScreen
import com.selmasu.kriskringle.ui.theme.KrisKringleTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge(
            statusBarStyle = SystemBarStyle.dark(android.graphics.Color.TRANSPARENT),
            navigationBarStyle = SystemBarStyle.dark(android.graphics.Color.TRANSPARENT)
        )
        setContent { KrisKringleTheme { KrisKringleApp() } }
    }
}

@Composable
fun KrisKringleApp(model: AuthViewModel = viewModel()) {
    Box(Modifier.fillMaxSize()) {
        Image(
            painterResource(if (model.signedIn) R.drawable.christmas_background else R.drawable.login_background),
            contentDescription = null, contentScale = ContentScale.Crop, modifier = Modifier.matchParentSize()
        )
        Box(Modifier.matchParentSize().background(Color.Black.copy(alpha = if (model.signedIn) .2f else .15f)))
        when {
            model.restoring -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                Column(horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(16.dp)) {
                    CircularProgressIndicator()
                    Text("Loading Kris Kringle…")
                }
            }
            model.restoreError != null -> Box(Modifier.fillMaxSize().safeDrawingPadding().padding(32.dp), contentAlignment = Alignment.Center) {
                Card {
                    Column(Modifier.padding(24.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
                        Text("Couldn’t restore your session", style = MaterialTheme.typography.titleLarge)
                        Text(model.restoreError.orEmpty())
                        Button(onClick = model::restore) { Text("Try Again") }
                        TextButton(onClick = model::signedOut) { Text("Return to Sign In") }
                    }
                }
            }
            model.signedIn -> SignedInApp(model::signedOut)
            else -> LoginScreen(model)
        }
    }
}

@Composable
private fun SignedInApp(onSignedOut: () -> Unit) {
    val context = LocalContext.current.applicationContext
    var tab by rememberSaveable { mutableIntStateOf(0) }
    var profileVersion by remember { mutableIntStateOf(0) }
    var profile by remember { mutableStateOf<MyProfile?>(null) }
    DisposableEffect(profileVersion) {
        val service = UserService(object : UserServiceListener {
            override fun userServiceDone(type: UserService.Type, data: UserServiceData) { profile = data.profile }
            override fun serviceError(message: String) { /* The profile screen provides loading and retry controls. */ }
        }, context)
        service.getMyProfile()
        onDispose { service.cancel() }
    }
    Scaffold(
        containerColor = Color.Transparent,
        bottomBar = {
            NavigationBar(containerColor = MaterialTheme.colorScheme.surface.copy(alpha = .94f)) {
                NavigationBarItem(
                    selected = tab == 0, onClick = { tab = 0 },
                    icon = { Icon(painterResource(R.drawable.ic_gift), contentDescription = null) },
                    label = { Text("Kris Kringles") }
                )
                NavigationBarItem(
                    selected = tab == 1, onClick = { tab = 1 },
                    icon = {
                        ProfileAvatar(picture = profile?.picture, name = profile?.name ?: Globals.user?.name.orEmpty(),
                            size = 28.dp, pictureURL = Globals.user?.picture)
                    },
                    label = { Text("Me") }
                )
            }
        }
    ) { padding ->
        Box(Modifier.padding(padding).fillMaxSize()) {
            if (tab == 0) ExchangesScreen(onSessionExpired = onSignedOut, viewerProfile = profile)
            else ProfileScreen(onSignedOut = onSignedOut, onProfileChanged = { profileVersion++ })
        }
    }
}
