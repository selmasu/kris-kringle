package com.selmasu.kriskringle.ui.components

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Canvas
import android.graphics.Color as AndroidColor
import android.graphics.ImageDecoder
import android.graphics.Matrix
import androidx.exifinterface.media.ExifInterface
import android.net.Uri
import android.os.Build
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.annotation.RequiresApi
import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.ByteArrayOutputStream
import java.io.IOException
import kotlin.math.max
import kotlin.math.roundToInt

/** A system photo picker that prepares the same bounded JPEG upload used by the iOS app. */
@Composable
fun PhotoPicker(
    picture: ByteArray?,
    onPictureChange: (ByteArray?) -> Unit,
    enabled: Boolean = true,
    onPreparingChange: (Boolean) -> Unit = {}
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val pictureChanged by rememberUpdatedState(onPictureChange)
    val preparingChanged by rememberUpdatedState(onPreparingChange)
    var preparing by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    val launcher = rememberLauncherForActivityResult(ActivityResultContracts.PickVisualMedia()) { uri ->
        if (uri != null) {
            scope.launch {
                preparing = true
                preparingChanged(true)
                error = null
                try {
                    val jpeg = withContext(Dispatchers.IO) { preparePhoto(context, uri) }
                    pictureChanged(jpeg)
                } catch (cancelled: CancellationException) {
                    throw cancelled
                } catch (_: Exception) {
                    error = "Couldn’t prepare that photo. Try a different one."
                } finally {
                    preparing = false
                    preparingChanged(false)
                }
            }
        }
    }
    Column {
        picture?.let { bytes ->
            val bitmap = remember(bytes) { BitmapFactory.decodeByteArray(bytes, 0, bytes.size) }
            bitmap?.let {
                Image(
                    bitmap = it.asImageBitmap(), contentDescription = "Selected photo",
                    modifier = Modifier.fillMaxWidth().heightIn(max = 180.dp)
                        .clip(MaterialTheme.shapes.medium), contentScale = ContentScale.Fit
                )
                Spacer(Modifier.height(8.dp))
            }
        }
        TextButton(
            onClick = { launcher.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly)) },
            enabled = enabled && !preparing
        ) { Text(if (picture == null) "Choose Photo" else "Choose Another Photo") }
        if (preparing) {
            CircularProgressIndicator()
            Text("Preparing photo…", style = MaterialTheme.typography.bodySmall)
        }
        error?.let { Text(it, color = MaterialTheme.colorScheme.error) }
        if (picture != null) {
            TextButton(onClick = { error = null; pictureChanged(null) }, enabled = enabled && !preparing) {
                Text("Clear Selection")
            }
        }
    }
}

private fun preparePhoto(context: Context, uri: Uri): ByteArray {
    val decoded = if (Build.VERSION.SDK_INT >= 28) decodeModern(context, uri) else decodeLegacy(context, uri)
    try {
        val scaled = if (max(decoded.width, decoded.height) > 1024) {
            val ratio = 1024f / max(decoded.width, decoded.height)
            Bitmap.createScaledBitmap(decoded, (decoded.width * ratio).roundToInt().coerceAtLeast(1),
                (decoded.height * ratio).roundToInt().coerceAtLeast(1), true)
        } else decoded
        try {
            val jpegBitmap = Bitmap.createBitmap(scaled.width, scaled.height, Bitmap.Config.ARGB_8888)
            try {
                Canvas(jpegBitmap).apply { drawColor(AndroidColor.WHITE); drawBitmap(scaled, 0f, 0f, null) }
                for (quality in listOf(84, 65, 45, 30)) {
                    val output = ByteArrayOutputStream()
                    if (jpegBitmap.compress(Bitmap.CompressFormat.JPEG, quality, output) && output.size() <= 512 * 1024) {
                        return output.toByteArray()
                    }
                }
                throw IOException("Photo exceeds upload limit")
            } finally { jpegBitmap.recycle() }
        } finally { if (scaled !== decoded) scaled.recycle() }
    } finally { decoded.recycle() }
}

@RequiresApi(28)
private fun decodeModern(context: Context, uri: Uri): Bitmap =
    ImageDecoder.decodeBitmap(ImageDecoder.createSource(context.contentResolver, uri)) { decoder, info, _ ->
        val ratio = (1024f / max(info.size.width, info.size.height)).coerceAtMost(1f)
        decoder.setTargetSize((info.size.width * ratio).roundToInt().coerceAtLeast(1),
            (info.size.height * ratio).roundToInt().coerceAtLeast(1))
        decoder.allocator = ImageDecoder.ALLOCATOR_SOFTWARE
    }

private fun decodeLegacy(context: Context, uri: Uri): Bitmap {
    val resolver = context.contentResolver
    val options = BitmapFactory.Options().apply { inJustDecodeBounds = true }
    resolver.openInputStream(uri)?.use { BitmapFactory.decodeStream(it, null, options); Unit }
        ?: throw IOException("Photo is unavailable")
    if (options.outWidth <= 0 || options.outHeight <= 0) throw IOException("Unsupported photo")
    options.inJustDecodeBounds = false
    options.inSampleSize = 1
    while (max(options.outWidth, options.outHeight) / options.inSampleSize > 2048) options.inSampleSize *= 2
    val bitmap = resolver.openInputStream(uri)?.use { BitmapFactory.decodeStream(it, null, options) }
        ?: throw IOException("Could not decode photo")
    val orientation = resolver.openInputStream(uri)?.use {
        runCatching { ExifInterface(it).getAttributeInt(ExifInterface.TAG_ORIENTATION, ExifInterface.ORIENTATION_NORMAL) }
            .getOrDefault(ExifInterface.ORIENTATION_NORMAL)
    } ?: ExifInterface.ORIENTATION_NORMAL
    val transform = Matrix().apply {
        when (orientation) {
            ExifInterface.ORIENTATION_FLIP_HORIZONTAL -> setScale(-1f, 1f)
            ExifInterface.ORIENTATION_ROTATE_180 -> setRotate(180f)
            ExifInterface.ORIENTATION_FLIP_VERTICAL -> setScale(1f, -1f)
            ExifInterface.ORIENTATION_TRANSPOSE -> { setRotate(90f); postScale(-1f, 1f) }
            ExifInterface.ORIENTATION_ROTATE_90 -> setRotate(90f)
            ExifInterface.ORIENTATION_TRANSVERSE -> { setRotate(-90f); postScale(-1f, 1f) }
            ExifInterface.ORIENTATION_ROTATE_270 -> setRotate(-90f)
        }
    }
    if (transform.isIdentity) return bitmap
    return try { Bitmap.createBitmap(bitmap, 0, 0, bitmap.width, bitmap.height, transform, true) }
    finally { bitmap.recycle() }
}
