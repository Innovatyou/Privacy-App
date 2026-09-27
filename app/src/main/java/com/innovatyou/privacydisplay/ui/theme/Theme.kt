package com.innovatyou.privacydisplay.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Shapes
import androidx.compose.material3.Typography
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.innovatyou.privacydisplay.data.ThemeMode

// A calm blue palette with high-contrast text (WCAG AA or better for body text on surfaces).
private val LightColors = lightColorScheme(
    primary = Color(0xFF2456D1),
    onPrimary = Color.White,
    primaryContainer = Color(0xFFDCE4FF),
    onPrimaryContainer = Color(0xFF001550),
    secondary = Color(0xFF3F5F72),
    onSecondary = Color.White,
    secondaryContainer = Color(0xFFD5E6F3),
    onSecondaryContainer = Color(0xFF0D1F2A),
    tertiary = Color(0xFF7A4F9A),
    background = Color(0xFFF5F6FA),
    onBackground = Color(0xFF15181E),
    surface = Color(0xFFF5F6FA),
    onSurface = Color(0xFF15181E),
    surfaceVariant = Color(0xFFE3E6EE),
    onSurfaceVariant = Color(0xFF42464F),
    surfaceContainerLowest = Color.White,
    surfaceContainerLow = Color(0xFFFFFFFF),
    surfaceContainer = Color(0xFFEEF0F6),
    surfaceContainerHigh = Color(0xFFE8EAF1),
    surfaceContainerHighest = Color(0xFFE2E5EC),
    outline = Color(0xFF72777F),
    outlineVariant = Color(0xFFC3C7CF),
    error = Color(0xFFBA1A1A),
)

private val DarkColors = darkColorScheme(
    primary = Color(0xFFB3C5FF),
    onPrimary = Color(0xFF002A78),
    primaryContainer = Color(0xFF1B3FA8),
    onPrimaryContainer = Color(0xFFDCE4FF),
    secondary = Color(0xFFB9CAD8),
    onSecondary = Color(0xFF233441),
    secondaryContainer = Color(0xFF3A4B58),
    onSecondaryContainer = Color(0xFFD5E6F3),
    tertiary = Color(0xFFE0B6FF),
    background = Color(0xFF0F1115),
    onBackground = Color(0xFFE3E5EB),
    surface = Color(0xFF0F1115),
    onSurface = Color(0xFFE3E5EB),
    surfaceVariant = Color(0xFF2A2E36),
    onSurfaceVariant = Color(0xFFC3C7CF),
    surfaceContainerLowest = Color(0xFF0A0C0F),
    surfaceContainerLow = Color(0xFF1A1D23),
    surfaceContainer = Color(0xFF1E2128),
    surfaceContainerHigh = Color(0xFF252932),
    surfaceContainerHighest = Color(0xFF2F333C),
    outline = Color(0xFF8C9199),
    outlineVariant = Color(0xFF42464F),
    error = Color(0xFFFFB4AB),
)

private val AppShapes = Shapes(
    small = RoundedCornerShape(12.dp),
    medium = RoundedCornerShape(20.dp),
    large = RoundedCornerShape(28.dp),
    extraLarge = RoundedCornerShape(32.dp),
)

private val BaseTypography = Typography()
private val AppTypography = BaseTypography.copy(
    displaySmall = BaseTypography.displaySmall.copy(fontWeight = FontWeight.SemiBold, fontSize = 34.sp),
    titleLarge = BaseTypography.titleLarge.copy(fontWeight = FontWeight.SemiBold),
    titleMedium = BaseTypography.titleMedium.copy(fontWeight = FontWeight.SemiBold),
)

@Composable
fun PrivacyDisplayTheme(
    themeMode: ThemeMode = ThemeMode.SYSTEM,
    content: @Composable () -> Unit,
) {
    val dark = when (themeMode) {
        ThemeMode.SYSTEM -> isSystemInDarkTheme()
        ThemeMode.LIGHT -> false
        ThemeMode.DARK -> true
    }
    MaterialTheme(
        colorScheme = if (dark) DarkColors else LightColors,
        typography = AppTypography,
        shapes = AppShapes,
        content = content,
    )
}
