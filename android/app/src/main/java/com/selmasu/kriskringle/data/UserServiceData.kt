package com.selmasu.kriskringle.data

import org.json.JSONObject

data class UserServiceData(
    val json: JSONObject = JSONObject(),
    val retryAfter: Int = 60,
    val profile: MyProfile? = null,
)
