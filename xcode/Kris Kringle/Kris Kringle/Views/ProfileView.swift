//
//  ProfileView.swift
//  Kris Kringle
//
//  Created by Lucas Sparke on 8/10/2026.
//

import SwiftUI

struct ProfileView: View, UserServiceDelegate {
    @Binding var isLoggedIn: Bool
    @State private var errorMessage = ""
    @State private var showError = false
    
    var body: some View {
        VStack {
            Spacer()
            Button(role: .destructive) {
                logout()
            } label: {
                Text("Log Out")
                    .frame(maxWidth: .infinity)
            }
            .buttonStyle(.borderedProminent)
            .tint(.red)
            .padding()
        }.alert("Unable to Log Out", isPresented: $showError) {
            Button("OK", role: .cancel) { }
        } message: {
            Text(errorMessage)
        }
    }
    
    func logout(){
        UserService(delegate: self).logout()
    }

    func userServiceDidComplete(_ type: UserServiceType, data: UserServiceData) {
        if type == .logout {
            isLoggedIn = false
        }
    }

    func serviceError(_ error: String) {
        errorMessage = error.isEmpty ? "Please try again." : error
        showError = true
    }
}

#Preview {
    ProfileView(isLoggedIn: .constant(true))
}
