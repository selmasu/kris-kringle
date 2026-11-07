//
//  StartView.swift
//  Kris Kringle
//
//  Created by Lucas Sparke on 5/11/2026.
//

import SwiftUI

struct StartView: View, UserServiceDelegate {
    @State var isLoggedIn: Bool = false
    @State var isLoading: Bool = true
    
    var body: some View {
        ZStack {
            if !isLoggedIn {
                GeometryReader { geometry in
                    Image("Background")
                        .resizable()
                        .scaledToFill()
                        .frame(width: geometry.size.width, height: geometry.size.height)
                        .clipped()
                        .accessibilityHidden(true)
                }
                .ignoresSafeArea()
            }
            
            if isLoading {
                ProgressView()
                    .tint(.white)
            } else if isLoggedIn {
                MainView(isLoggedIn: $isLoggedIn)
            } else {
                LoginView(isLoggedIn: $isLoggedIn)
            }
        }
        .onAppear {
            login()
        }
    }
    
    private func login(){
        if let user = UserService.lastToLogin {
            Globals.user(user)
            UserService(delegate: self).getUser()
        } else {
            isLoading = false
        }
    }
    
    func userServiceDidComplete(_ type: UserServiceType, data: UserServiceData) {
        isLoading = false
        isLoggedIn = true
    }
    
    func serviceError(_ error: String) {
        isLoading = false
        isLoggedIn = false
    }
}

#Preview {
    StartView()
}
