package com.selmasu.kriskringle.ui.theme

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.LocalContentColor
import androidx.compose.material3.darkColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.ui.graphics.Color

private val ChristmasColors = darkColorScheme(
    primary = ChristmasGold,
    onPrimary = Evergreen,
    primaryContainer = ChristmasGreen,
    onPrimaryContainer = Color.White,
    secondary = Color(0xFFB2D5B7),
    background = Evergreen,
    surface = Color(0xFF14392E),
    surfaceVariant = Color(0xFF27473C),
    onSurface = Color.White,
    onBackground = Color.White,
    onSurfaceVariant = Color(0xFFDBE5DE),
    error = Color(0xFFFFB4AB)
)

@Composable
fun KrisKringleTheme(content: @Composable () -> Unit) {
    MaterialTheme(colorScheme = ChristmasColors, typography = Typography) {
        CompositionLocalProvider(LocalContentColor provides ChristmasColors.onBackground, content = content)
    }
}
