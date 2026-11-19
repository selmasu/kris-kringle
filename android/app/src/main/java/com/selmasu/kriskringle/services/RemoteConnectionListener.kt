package com.selmasu.kriskringle.services

import org.json.JSONObject

interface RemoteConnectionListener {
    fun objectRequestComplete(data: JSONObject)
    fun remoteConnectionError(message: String)
}
