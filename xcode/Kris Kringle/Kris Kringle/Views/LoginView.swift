//
//  LoginView.swift
//  Kris Kringle
//
//  Created by Lucas Sparke on 5/11/2026.
//

import Foundation
import SwiftUI
import AuthenticationServices
import GoogleSignIn

struct LoginView: View, UserServiceDelegate {
    private enum Field: Hashable {
        case email, code
    }

    @State private var showAlert = false
    @State private var emailSignIn = false
    @State private var email = ""
    @State private var requestedEmail = ""
    @State private var oneTimeCode = ""
    @State private var codeSent = false
    @State private var isSubmitting = false
    @State private var resendAvailableAt = Date.distantPast
    @State private var alertMessage = ""
    @State private var appleSignInModel = AppleSignInModel()
    @StateObject private var googleSignInModel = GoogleSignInModel()
    @FocusState private var focusedField: Field?

    @Binding var isLoggedIn: Bool

    var body: some View {
        NavigationStack {
            ZStack {
                GeometryReader { geometry in
                    Image("Background")
                        .resizable()
                        .scaledToFill()
                        .frame(width: geometry.size.width, height: geometry.size.height)
                        .clipped()
                        .accessibilityHidden(true)
                }
                .ignoresSafeArea()

                VStack {
                    Spacer()
                    if emailSignIn {
                        emailCodeForm
                    } else {
                        signInOptions
                    }
                }
            }
            .alert("Unable to Continue", isPresented: $showAlert) {
                Button("OK", role: .cancel) { }
            } message: {
                Text(alertMessage)
            }
            .onChange(of: appleSignInModel.signInSuccess) {
                if appleSignInModel.signInSuccess {
                    signInWithApple()
                }
            }
            .onChange(of: googleSignInModel.signInSuccess) {
                if googleSignInModel.signInSuccess {
                    signInWithGoogle()
                }
            }
        }
    }

    private var emailCodeForm: some View {
        VStack(spacing: 16) {
            Text(codeSent ? "Check your email" : "Sign in or create an account")
                .font(.title2.bold())
                .multilineTextAlignment(.center)

            if codeSent {
                Text("Enter the six-digit code sent to \(requestedEmail). It expires in five minutes.")
                    .font(.subheadline)
                    .multilineTextAlignment(.center)

                TextField("6-digit code", text: $oneTimeCode)
                    .keyboardType(.numberPad)
                    .textContentType(.oneTimeCode)
                    .multilineTextAlignment(.center)
                    .focused($focusedField, equals: .code)
                    .padding()
                    .foregroundStyle(.black)
                    .background(.white, in: RoundedRectangle(cornerRadius: 23))
                    .disabled(isSubmitting)
                    .onChange(of: oneTimeCode) {
                        oneTimeCode = String(oneTimeCode.filter { $0 >= "0" && $0 <= "9" }.prefix(6))
                    }
            } else {
                Text("We'll email you a one-time code. No password needed.")
                    .font(.subheadline)
                    .multilineTextAlignment(.center)

                TextField("Email address", text: $email)
                    .textContentType(.emailAddress)
                    .keyboardType(.emailAddress)
                    .textInputAutocapitalization(.never)
                    .autocorrectionDisabled()
                    .submitLabel(.go)
                    .focused($focusedField, equals: .email)
                    .padding()
                    .foregroundStyle(.black)
                    .background(.white, in: RoundedRectangle(cornerRadius: 23))
                    .disabled(isSubmitting)
                    .onSubmit { requestCode() }
            }

            Button {
                if codeSent {
                    verifyCode()
                } else {
                    requestCode()
                }
            } label: {
                HStack {
                    if isSubmitting {
                        ProgressView().tint(.white)
                    }
                    Text(codeSent ? "Verify and Continue" : "Send Code")
                }
                .frame(maxWidth: .infinity, minHeight: 46)
                .background(Theme.green, in: RoundedRectangle(cornerRadius: 23))
            }
            .disabled(isSubmitting || (codeSent ? oneTimeCode.count != 6 : email.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty))

            if codeSent {
                TimelineView(.periodic(from: .now, by: 1)) { context in
                    let seconds = max(0, Int(ceil(resendAvailableAt.timeIntervalSince(context.date))))
                    Button(seconds > 0 ? "Resend code in \(seconds)s" : "Resend code") {
                        requestCode()
                    }
                    .disabled(isSubmitting || seconds > 0)
                }

                Button("Use a different email") {
                    codeSent = false
                    oneTimeCode = ""
                    focusedField = .email
                }
                .disabled(isSubmitting)
            }

            Button("Cancel") {
                emailSignIn = false
                codeSent = false
                oneTimeCode = ""
                focusedField = nil
            }
            .disabled(isSubmitting)
        }
        .foregroundStyle(.white)
        .tint(.white)
        .padding(20)
        .frame(maxWidth: 355)
        .background(.black.opacity(0.65), in: RoundedRectangle(cornerRadius: 24))
        .padding(.horizontal, 20)
        .padding(.bottom, 32)
    }

    private var signInOptions: some View {
        VStack(spacing: 10) {
            Button {
                emailSignIn = true
                focusedField = .email
            } label: {
                HStack {
                    Spacer()
                    Image(systemName: "envelope")
                    Text("Continue with Email")
                    Spacer()
                }
                .padding(.horizontal, 16)
            }

            Button(action: { appleSignInModel.signInWithApple() }) {
                HStack {
                    Spacer()
                    Image("Apple")
                        .resizable()
                        .aspectRatio(contentMode: .fill)
                        .frame(width: 20)
                    Text("Sign in with Apple")
                    Spacer()
                }
                .padding(.horizontal, 16)
            }

            Button(action: { googleSignInModel.signIn() }) {
                HStack {
                    Spacer()
                    Image("Google Logo")
                        .resizable()
                        .aspectRatio(contentMode: .fit)
                        .frame(width: 20)
                    Text("Sign in with Google")
                    Spacer()
                }
                .padding(.horizontal, 16)
            }
        }
        .buttonStyle(SignInOptionStyle())
        .disabled(isSubmitting)
        .padding(.bottom, 32)
    }

    private func requestCode() {
        guard !isSubmitting else { return }
        if codeSent && Date() < resendAvailableAt { return }
        let address = codeSent ? requestedEmail : email.trimmingCharacters(in: .whitespacesAndNewlines).lowercased()
        guard address.contains("@"), !address.contains(where: { $0.isWhitespace }) else {
            serviceError("Please enter a valid email address.")
            return
        }
        email = address
        requestedEmail = address
        isSubmitting = true
        focusedField = nil
        UserService(delegate: self).sendOneTimeCode(address)
    }

    private func verifyCode() {
        guard !isSubmitting, codeSent, oneTimeCode.count == 6 else { return }
        isSubmitting = true
        focusedField = nil
        UserService(delegate: self).loginOneTimeCode(requestedEmail, code: oneTimeCode)
    }

    private func signInWithApple() {
        isSubmitting = true
        UserService(delegate: self).loginApple(appleSignInModel.authenticationCode, firstName: appleSignInModel.firstName, lastName: appleSignInModel.lastName)
    }

    private func signInWithGoogle() {
        isSubmitting = true
        UserService(delegate: self).loginGoogle(googleSignInModel.idToken)
    }

    func userServiceDidComplete(_ type: UserServiceType, data: UserServiceData) {
        isSubmitting = false
        if type == .oneTimeCodeSent {
            codeSent = true
            oneTimeCode = ""
            resendAvailableAt = Date().addingTimeInterval(TimeInterval(data.retryAfter))
            focusedField = .code
        } else if type == .login {
            isLoggedIn = true
        }
    }

    func serviceError(_ error: String) {
        isSubmitting = false
        alertMessage = error.isEmpty ? "Please try again." : error
        showAlert = true
    }
}

private struct SignInOptionStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .foregroundStyle(.black)
            .frame(width: 315, height: 46)
            .background(.white, in: RoundedRectangle(cornerRadius: 23))
            .opacity(configuration.isPressed ? 0.7 : 1)
    }
}

@Observable
class AppleSignInModel: NSObject, ASAuthorizationControllerDelegate {
    var authenticationCode: String = ""
    var firstName: String = ""
    var lastName: String = ""
    var signInSuccess: Bool = false
    
    func signInWithApple(){
        let provider = ASAuthorizationAppleIDProvider()
        let request = provider.createRequest()
        
        request.requestedScopes = [.fullName, .email]
        let controller = ASAuthorizationController(authorizationRequests: [request])
        controller.delegate = self
        controller.performRequests()
    }
    
    func authorizationController(controller: ASAuthorizationController, didCompleteWithAuthorization authorization: ASAuthorization) {
        switch authorization.credential {
            case let appleIDCredential as ASAuthorizationAppleIDCredential:
                if let authorizationCode = appleIDCredential.authorizationCode {
                    if let authCode = String(data: authorizationCode, encoding: .utf8) {
                        if let fullName = appleIDCredential.fullName {
                            authenticationCode = authCode
                            if let givenName = fullName.givenName {
                                firstName = givenName
                            }
                            if let familyName = fullName.familyName {
                                lastName = familyName
                            }
                        }
                        
                        signInSuccess = true
                    }
                }
                
            default:
                break
        }
    }
    
    func authorizationController(controller: ASAuthorizationController, didCompleteWithError error: Error) {
        print(error)
    }
}



#Preview {
    LoginView(isLoggedIn: .constant(false))
}
