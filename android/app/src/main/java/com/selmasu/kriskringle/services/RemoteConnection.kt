package com.selmasu.kriskringle.services

import android.content.Context
import com.android.volley.DefaultRetryPolicy
import com.android.volley.NetworkResponse
import com.android.volley.ParseError
import com.android.volley.Request
import com.android.volley.RequestQueue
import com.android.volley.Response
import com.android.volley.VolleyError
import com.android.volley.toolbox.HttpHeaderParser
import com.android.volley.toolbox.Volley
import com.selmasu.kriskringle.data.Constants
import com.selmasu.kriskringle.data.Globals
import org.json.JSONObject
import java.io.ByteArrayOutputStream
import java.net.URLEncoder
import java.util.UUID

/** A shared Volley queue with per-request listeners, form fields, and optional JPEG uploads. */
class RemoteConnection(private val listener: RemoteConnectionListener, context: Context) {
    private val queue = requestQueue(context)
    private val requestTag = Any()

    fun requestObject(
        app: String,
        service: String,
        function: String,
        fields: Map<String, String> = emptyMap(),
        picture: ByteArray? = null,
        authenticated: Boolean = true,
        allowNull: Boolean = false,
    ) {
        val sessionToken = Globals.token
        if (authenticated && sessionToken.isEmpty()) {
            listener.remoteConnectionError("Please sign in again.")
            return
        }
        val parameters = fields + ("selmasuToken" to sessionToken)
        val boundary = "KrisKringle${UUID.randomUUID()}"
        val body = if (picture == null) encodeFields(parameters).toByteArray(Charsets.UTF_8)
            else multipart(parameters, picture, boundary)
        val request = object : Request<JSONObject>(Request.Method.POST, endpoint(app, service, function),
            Response.ErrorListener { listener.remoteConnectionError(errorMessage(it)) }) {
            override fun getBody(): ByteArray = body
            override fun getBodyContentType(): String = if (picture == null)
                "application/x-www-form-urlencoded; charset=UTF-8" else "multipart/form-data; boundary=$boundary"
            override fun parseNetworkResponse(response: NetworkResponse): Response<JSONObject> = try {
                Response.success(decodeResponse(response.data, allowNull), HttpHeaderParser.parseCacheHeaders(response))
            } catch (error: Exception) {
                Response.error(ParseError(error))
            }
            override fun deliverResponse(response: JSONObject) {
                if (authenticated && sessionToken != Globals.token) {
                    listener.remoteConnectionError("Your session changed. Please try again.")
                } else {
                    val error = serverError(response)
                    if (error != null) listener.remoteConnectionError(error)
                    else listener.objectRequestComplete(response)
                }
            }
        }
        enqueue(request)
    }

    fun requestBytes(app: String, service: String, function: String, fields: Map<String, String>,
                     onSuccess: (ByteArray) -> Unit, onError: (String) -> Unit) {
        val token = Globals.token
        if (token.isEmpty()) {
            onError("Please sign in again.")
            return
        }
        val body = encodeFields(fields + ("selmasuToken" to token)).toByteArray(Charsets.UTF_8)
        val request = object : Request<ByteArray>(Request.Method.POST, endpoint(app, service, function),
            Response.ErrorListener { onError(errorMessage(it)) }) {
            override fun getBody(): ByteArray = body
            override fun getBodyContentType(): String = "application/x-www-form-urlencoded; charset=UTF-8"
            override fun parseNetworkResponse(response: NetworkResponse): Response<ByteArray> =
                Response.success(response.data, HttpHeaderParser.parseCacheHeaders(response))
            override fun deliverResponse(response: ByteArray) {
                if (token == Globals.token) onSuccess(response) else onError("Your session changed. Please try again.")
            }
        }
        enqueue(request)
    }

    fun cancel() { queue.cancelAll(requestTag) }

    private fun <T> enqueue(request: Request<T>) {
        request.tag = requestTag
        request.setShouldCache(false)
        // Retrying a mutation could create a second group or send an invitation twice.
        request.retryPolicy = DefaultRetryPolicy(25_000, 0, 1f)
        queue.add(request)
    }

    companion object {
        @Volatile private var sharedQueue: RequestQueue? = null
        private fun requestQueue(context: Context): RequestQueue = sharedQueue ?: synchronized(this) {
            sharedQueue ?: Volley.newRequestQueue(context.applicationContext).also { sharedQueue = it }
        }
        private fun endpoint(app: String, service: String, function: String): String =
            "${Constants.APP_URL}/json/$app/$service/$function"
        internal fun encodeFields(fields: Map<String, String>): String = fields.entries.joinToString("&") {
            "${URLEncoder.encode(it.key, "UTF-8")}=${URLEncoder.encode(it.value, "UTF-8") }"
        }
        private fun multipart(fields: Map<String, String>, picture: ByteArray, boundary: String): ByteArray {
            val output = ByteArrayOutputStream()
            fun write(text: String) { output.write(text.toByteArray(Charsets.UTF_8)) }
            fields.forEach { (name, value) ->
                write("--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n")
            }
            write("--$boundary\r\nContent-Disposition: form-data; name=\"picture\"; filename=\"photo.jpg\"\r\nContent-Type: image/jpeg\r\n\r\n")
            output.write(picture)
            write("\r\n--$boundary--\r\n")
            return output.toByteArray()
        }
        internal fun decodeResponse(data: ByteArray, allowNull: Boolean): JSONObject {
            val value = String(data, Charsets.UTF_8).trim()
            return if (allowNull && (value.isEmpty() || value == "null")) {
                JSONObject().put("_nullResponse", true)
            } else {
                JSONObject(value)
            }
        }
        internal fun serverError(response: JSONObject): String? = sequenceOf("errorMessage", "error")
            .mapNotNull { response.opt(it) as? String }
            .firstOrNull { it.isNotBlank() && it != "null" }

        private fun errorMessage(error: VolleyError): String {
            val body = error.networkResponse?.data?.let { String(it, Charsets.UTF_8) }
            val serverMessage = body?.let { runCatching { serverError(JSONObject(it)) }.getOrNull() }
            return when {
                !serverMessage.isNullOrBlank() -> serverMessage
                error.networkResponse?.statusCode in listOf(401, 403) -> "Please sign in again."
                error is ParseError -> "The server returned an unexpected response. Please try again."
                else -> "Couldn’t reach the server. Please try again."
            }
        }
    }
}
