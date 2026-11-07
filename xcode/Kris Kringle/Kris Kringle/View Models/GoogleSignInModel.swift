//
//  GoogleSignInModel.swift
//  Kris Kringle
//
//  Created by Lucas Sparke on 5/11/2026.
//

import SwiftUI
import Combine
import UIKit
import GoogleSignIn

class GoogleSignInModel: ObservableObject {
    
    @Published var idToken: String = ""
    @Published var signInSuccess: Bool = false
    
    func signIn(){
        guard let presentingViewController = (UIApplication.shared.connectedScenes.first as? UIWindowScene)?.windows.first?.rootViewController else {return}

        GIDSignIn.sharedInstance.signIn(withPresenting: presentingViewController) { signInResult, error in
            guard error == nil else { return }
            guard let signInResult = signInResult else { return }

            let user = signInResult.user

            if let token = user.idToken {
                self.idToken = token.tokenString
                self.signInSuccess = true
            }
        }
    }
    
    func signOut(){
        GIDSignIn.sharedInstance.signOut()
    }
}
