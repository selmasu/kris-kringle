//
//  Globals.swift
//  simpli
//
//  Created by Lucas Sparke on 29/7/2022.
//

import Foundation
import CoreData
import UIKit
import Alamofire

let _globals:Globals = Globals()
class Globals{
    var _loggedIn = false
    var _user:User?
    var _online:Bool = true
    
    class var loggedIn:Bool {
        return _globals._loggedIn
    }
    
    class func loggedIn( _ loggedIn:Bool ) {
        _globals._loggedIn = loggedIn
    }
    
    class func user( _ user:User){
        _globals._user = user
    }
    
    class var user:User? {
        return _globals._user
    }
    
    class var userId:Int {
        if let user = Globals.user {
            return user.userId.intValue
        }
        
        return 0
    }
    
    class var name:String {
        if let user = Globals.user {
            return user.firstName + " " + user.lastName
        }
        
        return ""
    }
    
    class var online:Bool {
        return _globals._online
    }
    
    class func setOnline( _ online:Bool ) {
        _globals._online = online
    }
    
    class func saveContext() {
        do {
            try PersistenceController.get.container.viewContext.save()
        } catch {
            let nsError = error as NSError
            fatalError("Unresolved error \(nsError), \(nsError.userInfo)")
        }
    }
    
    class func deleteManagedObject(_ object:NSManagedObject) {
        PersistenceController.get.container.viewContext.delete(object)
    }
    
    class var context:NSManagedObjectContext {
        return PersistenceController.get.container.viewContext
    }
    
    class var appDocumentsDir:NSString {
        let paths = NSSearchPathForDirectoriesInDomains(FileManager.SearchPathDirectory.documentDirectory, FileManager.SearchPathDomainMask.userDomainMask, true)
        if paths.count > 0 {
            return paths[0] as NSString
        }
        
        return ""
    }
    
    class var selmasuToken:String {
        var token = ""
        if let selmasuToken = Globals.user?.token {
            token = selmasuToken
        }
        
        return token
    }
}
