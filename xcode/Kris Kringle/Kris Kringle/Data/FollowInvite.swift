//
//  FollowInvite.swift
//  Kris Kringle
//
//  Created by Lucas Sparke on 5/11/2026.
//

import Foundation
import SwiftyJSON

class FollowInvite {
    var userId = 0
    var statusId = 0
    var inviteEmail = ""
    var message = ""
    
    func fromJSON(_ data:JSON) -> FollowInvite {
        userId = data["userId"].intValue
        statusId = data["statusId"].intValue
        inviteEmail = data["inviteEmail"].stringValue
        message = data["message"].stringValue
        
        return self
    }
}
