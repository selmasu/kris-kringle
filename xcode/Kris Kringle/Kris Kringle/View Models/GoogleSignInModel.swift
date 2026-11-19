import SwiftUI
import Combine
import UIKit
import GoogleSignIn

@MainActor
class GoogleSignInModel: ObservableObject {
    @Published private(set) var isSigningIn = false

    func signIn(nonce: String? = nil) async throws -> String {
        guard !isSigningIn else { throw CancellationError() }
        guard let scene = UIApplication.shared.connectedScenes
            .compactMap({ $0 as? UIWindowScene })
            .first(where: { $0.activationState == .foregroundActive }),
              var presenter = scene.windows.first(where: \.isKeyWindow)?.rootViewController else {
            throw SignInError("Google sign-in couldn’t open. Please try again.")
        }
        while let presented = presenter.presentedViewController {
            presenter = presented
        }
        isSigningIn = true
        defer { isSigningIn = false }
        return try await withCheckedThrowingContinuation { continuation in
            GIDSignIn.sharedInstance.signIn(
                withPresenting: presenter, hint: nil, additionalScopes: nil, nonce: nonce
            ) { result, error in
                if let error {
                    continuation.resume(throwing: error)
                } else if let token = result?.user.idToken?.tokenString, !token.isEmpty {
                    continuation.resume(returning: token)
                } else {
                    continuation.resume(throwing: SignInError("Google didn’t return a sign-in token. Please try again."))
                }
            }
        }
    }

    static func isCancellation(_ error: Error) -> Bool {
        let error = error as NSError
        return error.domain == kGIDSignInErrorDomain && error.code == GIDSignInError.canceled.rawValue
    }

    private struct SignInError: LocalizedError {
        let message: String
        init(_ message: String) { self.message = message }
        var errorDescription: String? { message }
    }
}
