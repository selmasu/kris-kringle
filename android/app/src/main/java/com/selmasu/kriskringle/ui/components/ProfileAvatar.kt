package com.selmasu.kriskringle.ui.components

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.net.Uri
import android.widget.ImageView
import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.android.volley.DefaultRetryPolicy
import com.android.volley.RequestQueue
import com.android.volley.toolbox.ImageRequest
import com.android.volley.toolbox.Volley
import com.selmasu.kriskringle.data.Globals
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withContext
import kotlin.coroutines.resume
import kotlin.coroutines.resumeWithException
import kotlin.math.max

/** The saved profile photo takes precedence over the Google account picture. */
@Composable
fun ProfileAvatar(
    picture: ByteArray?,
    name: String,
    modifier: Modifier = Modifier,
    size: Dp = 96.dp,
    pictureURL: String? = null
) {
    val context = LocalContext.current
    val session = Globals.token
    var bitmap by remember(picture, pictureURL, session) { mutableStateOf<Bitmap?>(null) }
    LaunchedEffect(picture, pictureURL, session) {
        bitmap = null
        try {
            val local = picture?.let { withContext(Dispatchers.Default) { decodeAvatar(it) } }
            val loaded = local ?: pictureURL?.takeIf { url ->
                runCatching { Uri.parse(url).scheme?.lowercase() in listOf("https", "http") }.getOrDefault(false)
            }?.let { downloadAvatar(context, it) }
            if (session == Globals.token) bitmap = loaded
        } catch (cancelled: CancellationException) {
            throw cancelled
        } catch (_: Exception) {
            // Leave the initials visible when a provider picture is unavailable.
        }
    }
    val image = bitmap
    if (image != null) {
        Image(image.asImageBitmap(), contentDescription = "Profile photo", modifier = modifier.size(size).clip(CircleShape),
            contentScale = ContentScale.Crop)
    } else {
        Surface(modifier.size(size), shape = CircleShape, color = MaterialTheme.colorScheme.primaryContainer) {
            Column(verticalArrangement = Arrangement.Center, horizontalAlignment = Alignment.CenterHorizontally) {
                val trimmed = name.trim()
                val initial = if (trimmed.isEmpty()) "Me" else trimmed.substring(0, trimmed.offsetByCodePoints(0, 1)).uppercase()
                Text(initial, style = when {
                    size <= 40.dp -> MaterialTheme.typography.labelMedium
                    size <= 64.dp -> MaterialTheme.typography.titleLarge
                    else -> MaterialTheme.typography.headlineLarge
                })
            }
        }
    }
}

private fun decodeAvatar(bytes: ByteArray): Bitmap? {
    val options = BitmapFactory.Options().apply { inJustDecodeBounds = true }
    BitmapFactory.decodeByteArray(bytes, 0, bytes.size, options)
    if (options.outWidth <= 0 || options.outHeight <= 0) return null
    options.inJustDecodeBounds = false
    options.inSampleSize = 1
    while (max(options.outWidth, options.outHeight) / options.inSampleSize > 512) options.inSampleSize *= 2
    return BitmapFactory.decodeByteArray(bytes, 0, bytes.size, options)
}

private object AvatarDownloads {
    @Volatile private var queue: RequestQueue? = null
    fun queue(context: Context): RequestQueue = queue ?: synchronized(this) {
        queue ?: Volley.newRequestQueue(context.applicationContext).also { queue = it }
    }
}

private suspend fun downloadAvatar(context: Context, url: String): Bitmap = suspendCancellableCoroutine { continuation ->
    val request = ImageRequest(url, { image ->
        if (continuation.isActive) continuation.resume(image)
    }, 512, 512, ImageView.ScaleType.CENTER_CROP, Bitmap.Config.ARGB_8888, { error ->
        if (continuation.isActive) continuation.resumeWithException(error)
    })
    request.setShouldCache(false)
    request.retryPolicy = DefaultRetryPolicy(10_000, 0, 1f)
    continuation.invokeOnCancellation { request.cancel() }
    AvatarDownloads.queue(context).add(request)
}
