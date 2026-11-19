package com.selmasu.kriskringle.services

import com.selmasu.kriskringle.data.KrisKringleServiceData

interface KrisKringleServiceListener {
    fun krisKringleServiceDone(type: KrisKringleService.Type, data: KrisKringleServiceData)
    fun serviceError(message: String)
}
