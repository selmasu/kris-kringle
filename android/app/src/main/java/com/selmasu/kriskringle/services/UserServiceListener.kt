package com.selmasu.kriskringle.services

import com.selmasu.kriskringle.data.UserServiceData

interface UserServiceListener {
    fun userServiceDone(type: UserService.Type, data: UserServiceData)
    fun serviceError(message: String)
}
