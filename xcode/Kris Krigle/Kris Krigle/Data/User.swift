//
//  User.swift
//  simpli
//
//  Created by Lucas Sparke on 29/7/2022.
//

import Foundation
import CoreData
import SwiftyJSON

class User: NSManagedObject {
    
    @NSManaged var userId: NSNumber
    @NSManaged var hasBeenDeleted: NSNumber
    @NSManaged var dirty: NSNumber
    @NSManaged var firstName: String
    @NSManaged var lastName: String
    @NSManaged var name: String
    @NSManaged var email: String
    @NSManaged var picture: String
    @NSManaged var token: String
    @NSManaged var lastToLogin: NSNumber
    @NSManaged var postSort: NSNumber
    
    var following = false
    var requestStatusId = 0
    var inviteMessage = ""
    var requestMessage = ""
    
    func fromJSON(_ data:JSON){
        if let id = data["userId"].string {
            userId = NSNumber(value: NSString(string: id).integerValue)
            if let deleted = data["deleted"].string {
                hasBeenDeleted = NSString(string: deleted).boolValue as NSNumber
                dirty = false
            } else {
                dirty = true
            }
            
            firstName = data["firstName"].string ?? ""
            lastName = data["lastName"].string ?? ""
            name = data["displayName"].string ?? ""
            if name.isEmpty {
                name = firstName
            }
            email = data["email"].string ?? ""
            picture = data["picture"].string ?? ""
            
            if let statusId = data["requestStatusId"].string {
                requestStatusId = NSString(string: statusId).integerValue
            }
            if let message = data["inviteMessage"].string {
                inviteMessage = message
            }
            if let message = data["requestMessage"].string {
                requestMessage = message
            }
        }
    }
    
    func toDict() -> [String:AnyObject] {
        let keys = NSMutableArray()
        for key in self.entity.attributesByName.keys {
            keys.add( key )
        }
        
        return self.dictionaryWithValues(forKeys: keys as NSArray as! [String]) as [String : AnyObject]
    }
    
    func toJSON() -> JSON {
        let json:JSON = JSON(toDict())
        return json
    }
    
}
