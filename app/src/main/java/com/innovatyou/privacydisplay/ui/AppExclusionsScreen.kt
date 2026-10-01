package com.innovatyou.privacydisplay.ui

import android.content.pm.PackageManager
import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.selection.toggleable
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.Warning
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilledTonalButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.produceState
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.unit.dp
import androidx.core.graphics.drawable.toBitmap
import com.innovatyou.privacydisplay.R
import com.innovatyou.privacydisplay.data.InstalledApp
import com.innovatyou.privacydisplay.ui.components.InfoCard
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

fun appRowTag(packageName: String) = "app_row_$packageName"

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AppExclusionsScreen(
    apps: List<InstalledApp>?,
    recommended: List<InstalledApp>,
    excluded: Set<String>,
    usageAccessGranted: Boolean,
    onToggle: (String) -> Unit,
    onGrantUsageAccess: () -> Unit,
    onBack: () -> Unit,
    modifier: Modifier = Modifier,
) {
    var query by rememberSaveable { mutableStateOf("") }
    val recommendedPackages = remember(recommended) { recommended.map { it.packageName }.toSet() }
    val filtered = remember(apps, query, recommendedPackages) {
        apps.orEmpty().filter {
            it.packageName !in recommendedPackages &&
                (query.isBlank() || it.label.contains(query, ignoreCase = true))
        }
    }

    Scaffold(
        modifier = modifier,
        topBar = {
            TopAppBar(
                title = { Text(stringResource(R.string.exclusions_title)) },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = MaterialTheme.colorScheme.background),
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = stringResource(R.string.cd_back))
                    }
                },
            )
        },
    ) { padding ->
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
            contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
        ) {
            item {
                Text(
                    stringResource(R.string.exclusions_hint),
                    style = MaterialTheme.typography.bodyLarge,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(horizontal = 4.dp, vertical = 8.dp),
                )
            }
            if (!usageAccessGranted) {
                item {
                    InfoCard(
                        title = stringResource(R.string.perm_usage_title),
                        text = stringResource(R.string.usage_access_rationale),
                        icon = Icons.Filled.Warning,
                        action = {
                            FilledTonalButton(onClick = onGrantUsageAccess) { Text(stringResource(R.string.perm_grant)) }
                        },
                        modifier = Modifier.padding(vertical = 8.dp),
                    )
                }
            }
            if (recommended.isNotEmpty() && query.isBlank()) {
                item { ListHeader(stringResource(R.string.exclusions_recommended)) }
                item {
                    Text(
                        stringResource(R.string.exclusions_recommended_hint),
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(horizontal = 4.dp, vertical = 4.dp),
                    )
                }
                items(recommended, key = { "recommended_${it.packageName}" }) { app ->
                    AppRow(app = app, checked = app.packageName in excluded, onToggle = { onToggle(app.packageName) })
                }
                item { ListHeader(stringResource(R.string.exclusions_all_apps)) }
            }
            item {
                OutlinedTextField(
                    value = query,
                    onValueChange = { query = it },
                    label = { Text(stringResource(R.string.exclusions_search)) },
                    leadingIcon = { Icon(Icons.Filled.Search, contentDescription = null) },
                    singleLine = true,
                    keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 8.dp),
                )
            }
            when {
                apps == null -> item {
                    Box(Modifier.fillMaxWidth().padding(32.dp), contentAlignment = Alignment.Center) {
                        CircularProgressIndicator()
                    }
                }
                filtered.isEmpty() -> item {
                    Text(
                        stringResource(R.string.exclusions_empty),
                        modifier = Modifier.padding(16.dp),
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                else -> items(filtered, key = { it.packageName }) { app ->
                    AppRow(app = app, checked = app.packageName in excluded, onToggle = { onToggle(app.packageName) })
                }
            }
        }
    }
}

@Composable
private fun ListHeader(text: String) {
    Text(
        text,
        style = MaterialTheme.typography.titleSmall,
        color = MaterialTheme.colorScheme.primary,
        modifier = Modifier
            .padding(start = 4.dp, top = 16.dp, bottom = 4.dp)
            .semantics { heading() },
    )
}

@Composable
private fun AppRow(app: InstalledApp, checked: Boolean, onToggle: () -> Unit) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 60.dp)
            .toggleable(value = checked, role = Role.Checkbox, onValueChange = { onToggle() })
            .testTag(appRowTag(app.packageName))
            .padding(horizontal = 4.dp, vertical = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        AppIcon(app.packageName)
        Spacer(Modifier.width(16.dp))
        Text(app.label, style = MaterialTheme.typography.bodyLarge, modifier = Modifier.weight(1f))
        Checkbox(checked = checked, onCheckedChange = null)
    }
}

@Composable
private fun AppIcon(packageName: String) {
    val context = LocalContext.current
    val icon by produceState<ImageBitmap?>(initialValue = null, packageName) {
        value = withContext(Dispatchers.IO) {
            try {
                context.packageManager.getApplicationIcon(packageName).toBitmap(96, 96).asImageBitmap()
            } catch (e: PackageManager.NameNotFoundException) {
                null
            }
        }
    }
    val bitmap = icon
    if (bitmap != null) {
        Image(bitmap = bitmap, contentDescription = null, modifier = Modifier.size(40.dp))
    } else {
        Spacer(Modifier.size(40.dp))
    }
}
