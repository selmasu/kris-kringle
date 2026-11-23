import AuthenticationServices
import CryptoKit
import SwiftUI
import UIKit

struct ProfileView: View, UserServiceDelegate {
    @Binding var isLoggedIn: Bool
    @Binding var profile: MyProfile?
    var photo: UIImage?
    var loadError: String?
    var retry: () -> Void

    @State private var name: String?
    @State private var selectedPhoto: Data?
    @State private var isPreparingPhoto = false
    @State private var isSaving = false
    @State private var isLinking = false
    @State private var isLoggingOut = false
    @State private var isDeleting = false
    @State private var confirmDelete = false
    @State private var appleSalt = ""
    @State private var appleSessionToken = ""
    @State private var errorMessage: String?
    @State private var showError = false

    private var isBusy: Bool { isSaving || isLinking || isLoggingOut || isDeleting }
    private var nameBinding: Binding<String> {
        Binding(
            get: { name ?? profile?.name ?? "" },
            set: { name = $0 }
        )
    }
    private var trimmedName: String { nameBinding.wrappedValue.trimmingCharacters(in: .whitespacesAndNewlines) }
    private var nameChanged: Bool { trimmedName != profile?.name }
    private var canSave: Bool {
        (!nameChanged || !trimmedName.isEmpty) && (nameChanged || selectedPhoto != nil)
    }

    var body: some View {
        NavigationStack {
            Form {
                if let profile {
                    Section("Profile") {
                        if selectedPhoto == nil {
                            HStack {
                                Spacer()
                                avatar
                                Spacer()
                            }
                            .listRowSeparator(.hidden)
                            .translucentDarkRow()
                        }
                        PhotoPicker(picture: $selectedPhoto, isPreparing: $isPreparingPhoto, isSaving: isBusy)
                            .translucentDarkRow()
                        TextField("Display name", text: nameBinding)
                            .textContentType(.name)
                            .textInputAutocapitalization(.words)
                            .translucentDarkRow()
                        Button(isSaving ? "Saving…" : "Save Profile") { saveProfile() }
                            .disabled(!canSave || isPreparingPhoto)
                            .translucentDarkRow()
                    }
                    .disabled(isBusy)

                    Section {
                        if profile.appleLinked {
                            Label("Apple Account Linked", systemImage: "checkmark.circle.fill")
                                .translucentDarkRow()
                        } else {
                            SignInWithAppleButton(.continue) { request in
                                appleSalt = UUID().uuidString
                                appleSessionToken = Globals.selmasuToken
                                // Bind the authorization to the same Kris Kringle session that submits it.
                                let input = Data("\(appleSessionToken):\(appleSalt)".utf8)
                                request.nonce = SHA256.hash(data: input).map { String(format: "%02x", $0) }.joined()
                                isLinking = true
                            } onCompletion: { result in
                                finishAppleLink(result)
                            }
                            .signInWithAppleButtonStyle(.white)
                            .frame(height: 44)
                            .clipShape(RoundedRectangle(cornerRadius: 6))
                            .accessibilityLabel("Link Apple Account")
                            .translucentDarkRow()
                            if isLinking { ProgressView("Linking Apple account…").translucentDarkRow() }
                        }
                    } header: {
                        Text("Sign in with Apple")
                    } footer: {
                        if !profile.appleLinked {
                            Text("Link your Apple account to use it to sign in to this Kris Kringle account.")
                                .foregroundStyle(.white)
                                .shadow(color: .black.opacity(0.85), radius: 2)
                        }
                    }
                    .disabled(isBusy)
                } else if let loadError {
                    Section {
                        Text(loadError).translucentDarkRow()
                        Button("Try Again", action: retry).translucentDarkRow()
                    }
                } else {
                    ProgressView("Loading profile…").translucentDarkRow()
                }

                Section {
                    Button(isLoggingOut ? "Logging Out…" : "Log Out") {
                        isLoggingOut = true
                        UserService(delegate: self).logout()
                    }
                    .foregroundStyle(.white)
                    .disabled(isBusy)
                    .translucentDarkRow()
                    Button(isDeleting ? "Deleting Account…" : "Delete Account", role: .destructive) {
                        confirmDelete = true
                    }
                    .disabled(isBusy)
                    .translucentDarkRow()
                }
            }
            .christmasBackground()
            .navigationTitle("Me")
        }
        .confirmationDialog("Delete your account?", isPresented: $confirmDelete, titleVisibility: .visible) {
            Button("Delete Account", role: .destructive) {
                isDeleting = true
                UserService(delegate: self).deleteAccount()
            }
            Button("Cancel", role: .cancel) { }
        } message: {
            Text("Your account will be deleted and you’ll be signed out. You won’t be able to sign in to this account again.")
        }
        .alert("Profile", isPresented: $showError) {
            Button("OK", role: .cancel) { }
        } message: {
            Text(errorMessage ?? "Please try again.")
        }
    }

    @ViewBuilder
    private var avatar: some View {
        if let photo {
            Image(uiImage: photo)
                .resizable()
                .scaledToFill()
                .frame(width: 96, height: 96)
                .clipShape(Circle())
                .accessibilityLabel("Profile photo")
        } else {
            Image(systemName: "person.crop.circle")
                .font(.system(size: 88))
                .foregroundStyle(.secondary)
                .accessibilityHidden(true)
        }
    }

    private func saveProfile() {
        let updatedName = nameChanged ? trimmedName : nil
        let picture = selectedPhoto
        isSaving = true
        Task {
            defer { isSaving = false }
            do {
                try await ProfileService.save(name: updatedName, picture: picture)
                if let updatedName {
                    profile?.name = updatedName
                    Globals.user?.name = updatedName
                    Globals.saveContext()
                }
                if let picture {
                    profile?.picture = picture
                    selectedPhoto = nil
                }
            } catch is CancellationError {
            } catch {
                presentError(error.localizedDescription)
            }
        }
    }

    private func finishAppleLink(_ result: Result<ASAuthorization, Error>) {
        guard appleSessionToken == Globals.selmasuToken, !appleSessionToken.isEmpty else {
            isLinking = false
            return
        }
        let salt = appleSalt
        appleSessionToken = ""
        appleSalt = ""
        switch result {
        case .success(let authorization):
            guard let credential = authorization.credential as? ASAuthorizationAppleIDCredential,
                  let data = credential.authorizationCode,
                  let code = String(data: data, encoding: .utf8) else {
                isLinking = false
                presentError("Apple couldn’t authorize this request. Please try again.")
                return
            }
            Task {
                defer { isLinking = false }
                do {
                    try await ProfileService.linkApple(code: code, salt: salt)
                    profile?.appleLinked = true
                } catch is CancellationError {
                } catch {
                    presentError(error.localizedDescription)
                }
            }
        case .failure(let error):
            isLinking = false
            if (error as? ASAuthorizationError)?.code != .canceled {
                presentError("Apple couldn’t authorize this request. Please try again.")
            }
        }
    }

    func userServiceDidComplete(_ type: UserServiceType, data: UserServiceData) {
        if type == .logout || type == .deleteAccount {
            isLoggingOut = false
            isDeleting = false
            profile = nil
            selectedPhoto = nil
            isLoggedIn = false
        }
    }

    func serviceError(_ error: String) {
        isLoggingOut = false
        isDeleting = false
        presentError(error)
    }

    private func presentError(_ message: String) {
        errorMessage = message.isEmpty ? "Please try again." : message
        showError = true
    }
}

#Preview {
    ProfileView(isLoggedIn: .constant(true), profile: .constant(MyProfile(name: "Taylor", appleLinked: false))) { }
}
