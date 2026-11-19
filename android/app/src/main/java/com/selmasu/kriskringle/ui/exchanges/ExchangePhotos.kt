package com.selmasu.kriskringle.ui.exchanges

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.util.LruCache
import android.widget.ImageView
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.dp
import com.android.volley.RequestQueue
import com.android.volley.toolbox.ImageRequest
import com.android.volley.toolbox.Volley
import com.selmasu.kriskringle.data.*
import com.selmasu.kriskringle.services.*
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withContext
import kotlin.coroutines.resume
import kotlin.coroutines.resumeWithException

internal class ExchangePhotoCache {
    private val images = object : LruCache<String, Bitmap>(8 * 1024 * 1024) {
        override fun sizeOf(key: String, value: Bitmap) = value.byteCount
    }
    private fun key(group: KrisKringleSummary, session: String = Globals.token) = "$session:${group.id}:${group.pictureVersion}"
    fun image(group: KrisKringleSummary): Bitmap? = images.get(key(group))
    fun store(group: KrisKringleSummary, picture: ByteArray?, session: String = Globals.token): Bitmap? {
        if (picture == null || picture.size > 512 * 1024) return null
        return decodePhoto(picture)?.also { images.put(key(group, session), it) }
    }
}

private fun decodePhoto(bytes: ByteArray, maxDimension: Int = 1024): Bitmap? {
    val bounds = BitmapFactory.Options().apply { inJustDecodeBounds = true }
    BitmapFactory.decodeByteArray(bytes, 0, bytes.size, bounds)
    if (bounds.outWidth <= 0 || bounds.outHeight <= 0) return null
    var sample = 1
    while (maxOf(bounds.outWidth, bounds.outHeight) / sample > maxDimension) sample *= 2
    return BitmapFactory.decodeByteArray(bytes, 0, bytes.size, BitmapFactory.Options().apply { inSampleSize = sample })
}

private fun imageService(context: Context) = KrisKringleService(object : KrisKringleServiceListener {
    override fun krisKringleServiceDone(type: KrisKringleService.Type, data: KrisKringleServiceData) = Unit
    override fun serviceError(message: String) = Unit
}, context.applicationContext)

private suspend fun pictureBytes(context: Context, id: Int, userId: Int? = null): ByteArray = suspendCancellableCoroutine { continuation ->
    val service = imageService(context)
    val success: (ByteArray) -> Unit = {
        if (continuation.isActive) {
            if (it.size <= 512 * 1024) continuation.resume(it)
            else continuation.resumeWithException(IllegalStateException("The photo is too large."))
        }
    }
    val failure: (String) -> Unit = {
        if (continuation.isActive) continuation.resumeWithException(IllegalStateException(it))
    }
    continuation.invokeOnCancellation { service.cancel() }
    if (userId == null) service.picture(id, success, failure) else service.memberPicture(id, userId, success, failure)
}

@Composable
internal fun ExchangePhoto(
    group: KrisKringleSummary,
    cache: ExchangePhotoCache,
    modifier: Modifier = Modifier,
    draft: ByteArray? = null,
    showError: Boolean = false
) {
    val context = LocalContext.current
    var bitmap by remember(group.id, group.pictureVersion) { mutableStateOf(cache.image(group)) }
    var draftBitmap by remember(draft) { mutableStateOf<Bitmap?>(null) }
    var failed by remember(group.id, group.pictureVersion) { mutableStateOf(false) }
    var retry by remember { mutableIntStateOf(0) }
    LaunchedEffect(draft) {
        draftBitmap = draft?.let { withContext(Dispatchers.Default) { decodePhoto(it) } }
    }
    LaunchedEffect(group.id, group.pictureVersion, retry) {
        bitmap = cache.image(group)
        failed = false
        if (bitmap == null && group.pictureVersion.isNotEmpty()) {
            val session = Globals.token
            try {
                val bytes = pictureBytes(context, group.id)
                val loaded = withContext(Dispatchers.Default) { cache.store(group, bytes, session) }
                if (session == Globals.token) {
                    bitmap = loaded
                    failed = loaded == null
                }
            } catch (cancelled: CancellationException) { throw cancelled }
            catch (_: Exception) { failed = true }
        }
    }
    Column {
        Box(modifier.clip(RoundedCornerShape(12.dp)).background(MaterialTheme.colorScheme.primaryContainer.copy(alpha = .65f)), contentAlignment = Alignment.Center) {
            val image = draftBitmap ?: bitmap
            if (image != null) {
                Image(image.asImageBitmap(), null, Modifier.fillMaxSize(), contentScale = ContentScale.Crop)
            } else {
                Text("🎁", style = MaterialTheme.typography.headlineMedium)
            }
        }
        if (showError && failed && draft == null) TextButton(onClick = { retry++ }) { Text("Couldn’t load photo. Retry") }
    }
}

private object AvatarRequests {
    private var queue: RequestQueue? = null
    fun queue(context: Context): RequestQueue = queue ?: Volley.newRequestQueue(context.applicationContext).also { queue = it }
}

private suspend fun remoteAvatar(context: Context, url: String): Bitmap = suspendCancellableCoroutine { continuation ->
    val request = ImageRequest(url, { if (continuation.isActive) continuation.resume(it) }, 128, 128,
        ImageView.ScaleType.CENTER_CROP, Bitmap.Config.ARGB_8888,
        { if (continuation.isActive) continuation.resumeWithException(it) })
    continuation.invokeOnCancellation { request.cancel() }
    AvatarRequests.queue(context).add(request)
}

@Composable
internal fun MemberAvatar(
    exchangeId: Int,
    member: KrisKringleMember,
    picture: ByteArray? = null,
    loadPicture: suspend (Context, Int, Int) -> ByteArray = { context, id, userId -> pictureBytes(context, id, userId) },
    loadURL: suspend (Context, String) -> Bitmap = ::remoteAvatar
) {
    val context = LocalContext.current
    val session = Globals.token
    val fallbackURL = member.pictureURL?.takeIf { it.isNotBlank() }
        ?: Globals.user?.takeIf { it.userId == member.id }?.picture?.takeIf { it.isNotBlank() }
    val currentLoadPicture by rememberUpdatedState(loadPicture)
    val currentLoadURL by rememberUpdatedState(loadURL)
    var retry by remember(exchangeId, member.id, session) { mutableIntStateOf(0) }
    var bitmap by remember(exchangeId, member.id, member.pictureVersion, fallbackURL, session, retry, picture) {
        mutableStateOf<Bitmap?>(null)
    }
    var failed by remember(exchangeId, member.id, member.pictureVersion, fallbackURL, session, retry, picture) {
        mutableStateOf(false)
    }
    LaunchedEffect(exchangeId, member.id, member.pictureVersion, fallbackURL, session, retry, picture) {
        // The member-photo endpoint can have an uploaded profile photo even when older
        // roster responses do not include pictureVersion or pictureURL metadata.
        val savedProfileImage = picture?.let { bytes ->
            withContext(Dispatchers.Default) { decodePhoto(bytes, 128) }
        }
        val authenticatedImage = savedProfileImage ?: try {
            val bytes = currentLoadPicture(context, exchangeId, member.id)
            withContext(Dispatchers.Default) { decodePhoto(bytes, 128) }
        } catch (cancelled: CancellationException) { throw cancelled }
        catch (_: Exception) { null }
        val loaded = authenticatedImage ?: fallbackURL?.let { url ->
            try { currentLoadURL(context, url) }
            catch (cancelled: CancellationException) { throw cancelled }
            catch (_: Exception) { null }
        }
        if (session == Globals.token) {
            bitmap = loaded
            failed = loaded == null
        }
    }
    val canRetry = failed && (picture != null || member.hasPicture || fallbackURL != null)
    val retryLabel = "Retry photo for ${member.displayName}"
    val retryModifier = if (canRetry) Modifier
        .clickable(onClickLabel = retryLabel) { retry++ }
        .semantics { contentDescription = retryLabel }
    else Modifier
    Box(
        Modifier.size(44.dp).clip(CircleShape)
            .background(MaterialTheme.colorScheme.primaryContainer).then(retryModifier),
        contentAlignment = Alignment.Center
    ) {
        val image = bitmap
        if (image != null) Image(image.asImageBitmap(), "Photo of ${member.displayName}", Modifier.fillMaxSize(), contentScale = ContentScale.Crop)
        else Text(member.displayName.take(1).uppercase(), style = MaterialTheme.typography.titleLarge)
    }
}
