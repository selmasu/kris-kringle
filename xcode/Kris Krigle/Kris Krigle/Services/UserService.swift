//
//  UserService.swift
//  simpli
//
//  Created by Lucas Sparke on 29/7/2022.
//

import Foundation
import CoreData
import SwiftyJSON
import Alamofire

enum UserServiceType {
    case login, logout, user, followingLists, inviteToFollow, declineInvite, acceptInvite, update, checkRequests, requestToFollow, declineRequest, acceptRequest, leave, deleteAccount, signUp
}

protocol UserServiceDelegate {
    func userServiceDidComplete(_ type:UserServiceType, data:UserServiceData)
    func serviceError(_ error:String)
}

class UserServiceData {
    var json:JSON!
    var following = [User]()
    var followers = [User]()
    var requestsSent = [User]()
    var requestsReceived = [User]()
    var invitesSent = [FollowInvite]()
    var invitesReceived = [User]()
    var suggested = [User]()
    var notUserEmails = [String]()
    var userEmails = [String]()
    var invites = 0
    var requests = 0
}

class UserService {
    var delegate: UserServiceDelegate
    var functionCall = ""
    var service = "users"
    var app = "users"
    var email = ""
    var password = ""
    
    init(delegate:UserServiceDelegate){
        self.delegate = delegate
    }
    
    class var lastToLogin: User? {
        let request = NSFetchRequest<NSFetchRequestResult>(entityName: "User")
        request.predicate = NSPredicate(format: "lastToLogin = 1")
        let users:[AnyObject] = try! Globals.context.fetch(request)
        
        for user in users as! [User] {
            return user
        }
        
        return nil
    }
    
    func clearLastToLogin() {
        let request = NSFetchRequest<NSFetchRequestResult>(entityName: "User")
        request.predicate = NSPredicate(format: "lastToLogin = 1")
        let users:[AnyObject] = try! Globals.context.fetch(request)
        
        for user in users as! [User] {
            user.lastToLogin = false
        }
        
        Globals.saveContext()
    }
    
    class func user( _ userId:Int ) -> User {
        if( userId == 0 ) {
            return UserService.newUser( userId )
        }
        let request = NSFetchRequest<NSFetchRequestResult>(entityName: "User")
        request.predicate = NSPredicate(format: "userId = %i", userId)
        let users:[AnyObject] = try! Globals.context.fetch(request)
        
        for user in users as! [User] {
            return user
        }
        
        return UserService.newUser( userId )
    }
    
    class func userByUserId( _ userId:Int ) -> User? {
        let request = NSFetchRequest<NSFetchRequestResult>(entityName: "User")
        request.predicate = NSPredicate(format: "userId = %i", userId)
        let users:[AnyObject] = try! Globals.context.fetch(request)
        
        for user in users as! [User] {
            return user
        }
        
        return nil
    }
    
    class func newUser(_ id:Int) -> User {
        let entity = NSEntityDescription.entity(forEntityName: "User", in: Globals.context)
        let user = User(entity: entity!, insertInto: Globals.context)
        
        user.userId = NSNumber(value: id)
        
        return user
    }
    
    func loginGoogle( _ idToken:String ) {
        app = "framework"
        service = "authentication"
        functionCall = "loginGoogle"
        
        callService(Utils.requestParams(["idToken": idToken, "clientId": Constants.googleClientId]))
    }
    
    func loginApple( _ code:String, firstName:String, lastName:String ) {
        app = "framework"
        service = "authentication"
        functionCall = "loginApple"
        
        callService(Utils.requestParams(["code": code, "firstName": firstName, "lastName": lastName, "clientId": "travel.simpli"]))
    }
    
    func loginEmail( _ email:String, password:String ) {
        app = "framework"
        service = "authentication"
        functionCall = "loginNormal"
        
        callService(Utils.requestParams(["email": email, "password": password]))
    }
    
    func signUp( _ email:String, password:String ) {
        self.email = email
        self.password = password
        self.functionCall = "signUp"
        
        callService(Utils.requestParams(["data": ["email": email, "password": password, "token": Constants.signUpToken]]))
    }
    
    func logout() {
        app = "framework"
        service = "authentication"
        functionCall = "logout"
        
        callService(Utils.requestParams([:]))
    }
    
    func deleteAccount() {
        functionCall = "deleteMyAccount"
        
        callService(Utils.requestParams([:]))
    }
    
    func getUser() {
        functionCall = "getUser"
        
        callService(Utils.requestParams([:]))
    }
    
    func updateUser() {
        functionCall = "updateUser"
        
        callService(Utils.requestParams(["data": ["firstName": Globals.user!.firstName, "lastName": Globals.user!.lastName, "displayName": Globals.user!.name, "picture": Globals.user!.picture]]))
    }
    
    func sendInvites(_ emails:[String], message: String) {
        functionCall = "invite"
        
        callService(Utils.requestParams(["data": ["emails": emails, "message": message] as [String : Any]]))
    }
    
    func checkRequests(_ emails:[String]) {
        functionCall = "checkRequests"
        
        callService(Utils.requestParams(["data": ["emails": emails] as [String : Any]]))
    }
    
    func sendRequests(_ emails:[String], message: String) {
        functionCall = "requestToJoin"
        
        callService(Utils.requestParams(["data": ["emails": emails, "message": message] as [String : Any]]))
    }
    
    func declineInvite(_ userId:Int) {
        functionCall = "declineInvite"
        
        callService(Utils.requestParams(["userId": userId]))
    }
    
    func acceptInvite(_ userId:Int) {
        functionCall = "acceptInvite"
        
        callService(Utils.requestParams(["userId": userId]))
    }
    
    func declineRequest(_ userId:Int) {
        functionCall = "declineRequest"
        
        callService(Utils.requestParams(["userId": userId]))
    }
    
    func acceptRequest(_ userId:Int) {
        functionCall = "acceptRequest"
        
        callService(Utils.requestParams(["userId": userId]))
    }
    
    func leave(_ userId:Int) {
        functionCall = "leave"
        
        callService(Utils.requestParams(["userId": userId]))
    }
    
    func callService(_ params: [String:AnyObject]) {
        AF.request(Utils.requestURL(app, service: service, function: functionCall), parameters: params)
            .response { response in
                if let value = response.value {
                    let result = JSON(value!)
                    self.serviceResult(result)
                } else {
                    self.delegate.serviceError("")
                }
        }
    }
    
    func serviceResult(_ result:JSON){
        if functionCall == "loginGoogle" {
            onLogin(result)
        } else if functionCall == "loginApple" {
            onLogin(result)
        } else if functionCall == "loginNormal" {
            onLogin(result)
        } else if functionCall == "signUp" {
            onSignUp(result)
        } else if functionCall == "logout" {
            clearLastToLogin()
            delegate.userServiceDidComplete(.logout, data: UserServiceData())
        } else if functionCall == "deleteMyAccount" {
            clearLastToLogin()
            delegate.userServiceDidComplete(.deleteAccount, data: UserServiceData())
        } else if functionCall == "getUser" {
            onUser(result)
        } else if functionCall == "invite" {
            onInvite(result)
        } else if functionCall == "checkRequests" {
            onCheckRequests(result)
        } else if functionCall == "declineInvite" {
            delegate.userServiceDidComplete(.declineInvite, data: UserServiceData())
        } else if functionCall == "acceptInvite" {
            delegate.userServiceDidComplete(.acceptInvite, data: UserServiceData())
        } else if functionCall == "declineRequest" {
            delegate.userServiceDidComplete(.declineRequest, data: UserServiceData())
        } else if functionCall == "acceptRequest" {
            delegate.userServiceDidComplete(.acceptRequest, data: UserServiceData())
        } else if functionCall == "leave" {
            delegate.userServiceDidComplete(.leave, data: UserServiceData())
        } else if functionCall == "updateUser" {
            delegate.userServiceDidComplete(.update, data: UserServiceData())
        }
    }
    
    private func saveUser( _ data: JSON ) {
        if let userId = data["userId"].string {
            let user = UserService.user(NSString(string:userId).integerValue)
            user.fromJSON(data)
            Globals.saveContext()
        }
    }
    
    func onLogin(_ result:JSON){
        if let error = result["error"].string {
            delegate.serviceError(error)
        } else if let token = result["token"].string {
            
            var user:User!
            if let existing = UserService.userByUserId(result["user"]["userId"].int!) {
                user = existing
            } else {
                user = UserService.newUser(result["user"]["userId"].int!)
            }
            
            clearLastToLogin()
            
            user.token = token
            user.lastToLogin = NSNumber(value: true as Bool)
            user.firstName = result["user"]["firstName"].string ?? ""
            user.lastName = result["user"]["lastName"].string ?? ""
            user.email = result["user"]["email"].string ?? ""
            user.picture = result["user"]["picture"].string ?? ""
            user.name = result["user"]["name"].string ?? user.firstName
            user.name = user.name.isEmpty ? user.firstName : user.name
            
            Globals.saveContext()
            Globals.user(user)
            Globals.loggedIn(true)
            
            delegate.userServiceDidComplete(.login, data: UserServiceData())
        }
    }
    
    func onSignUp(_ result:JSON){
        if let error = result["error"].string {
            delegate.serviceError(error)
        } else if let _ = result["userId"].int {
            loginEmail(email, password: password)
        }
    }
    
    func onUser(_ result:JSON){
        if let userId = result["userId"].int {
            if let user = UserService.userByUserId(userId) {
                user.firstName = result["firstName"].string ?? ""
                user.lastName = result["lastName"].string ?? ""
                user.picture = result["picture"].string ?? ""
                user.name = [result["displayName"].string, result["name"].string, user.firstName]
                    .compactMap { $0?.trimmingCharacters(in: .whitespacesAndNewlines) }
                    .first { !$0.isEmpty } ?? ""
                
                Globals.saveContext()
                Globals.user(user)

                delegate.userServiceDidComplete(.user, data: UserServiceData())
            } else {
                delegate.serviceError("No user record")
            }
        } else {
            delegate.serviceError("Not logged in")
        }
    }
    
    func onInvite(_ result:JSON){
        let data = UserServiceData()
        if let invites = result["invites"].int {
            data.invites = invites
        }
        
        delegate.userServiceDidComplete(.inviteToFollow, data: data)
    }
    
    func onCheckRequests(_ result:JSON){
        let data = UserServiceData()
        if let notUserEmails = result["notUserEmails"].array {
            for notUserEmail in notUserEmails {
                data.notUserEmails.append(notUserEmail.string!)
            }
        }
        if let userEmails = result["userEmails"].array {
            for userEmail in userEmails {
                data.userEmails.append(userEmail.string!)
            }
        }
        
        delegate.userServiceDidComplete(.checkRequests, data: data)
    }
}
