//
//  MainView.swift
//  Kris Kringle
//
//  Created by Lucas Sparke on 8/10/2026.
//

import SwiftUI
import UIKit

struct MainView: View {
    @StateObject private var keyboard = KeyboardObserver()
    
    @State private var selection = 0
    @State private var profile: MyProfile?
    @State private var profileLoadError: String?
    @State private var profileLoadAttempt = 0
    @State private var profilePhoto: UIImage?
    @State private var avatar: UIImage?
    @Binding var isLoggedIn: Bool

    var body: some View {
        if #available(iOS 26.0, *) {
            tabs.tabBarMinimizeBehavior(.onScrollDown)
        } else {
            tabs
        }
    }

    private var tabs: some View {
        TabView(selection: $selection) {
            NavigationStack {
                KrisKringlesView()
            }
                .tag(0)
                .tabItem {
                    Image(systemName: "gift")
                    Text("Kris Kringle")
                }
            
            ProfileView(
                isLoggedIn: $isLoggedIn, profile: $profile, photo: profilePhoto, loadError: profileLoadError
            ) {
                profileLoadAttempt += 1
            }
                .tag(1)
                .tabItem {
                    if let avatar {
                        Image(uiImage: avatar).renderingMode(.original)
                    } else {
                        Image(systemName: "person.crop.circle")
                    }
                    Text("Me")
                }
        }
        .tint(Theme.green)
        .task(id: profileLoadAttempt) {
            profileLoadError = nil
            do {
                profile = try await ProfileService.load()
            } catch is CancellationError {
            } catch {
                profileLoadError = error.localizedDescription
            }
        }
        .task(id: profile?.picture) {
            await loadAvatar()
        }
    }

    @MainActor
    private func loadAvatar() async {
        if let picture = profile?.picture, let image = UIImage(data: picture) {
            setAvatar(image)
            return
        }
        guard let picture = Globals.user?.picture,
              !picture.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty,
              let url = URL(string: picture) else { return }

        do {
            let (data, response) = try await URLSession.shared.data(from: url)
            guard !Task.isCancelled,
                  let response = response as? HTTPURLResponse,
                  (200..<300).contains(response.statusCode),
                  let image = UIImage(data: data),
                  image.size.width > 0, image.size.height > 0 else { return }

            setAvatar(image)
        } catch {
            // Keep the default Me icon when the picture cannot be loaded.
        }
    }

    private func setAvatar(_ image: UIImage) {
        profilePhoto = image
        // Tab items need a pre-sized image; crop to a circle before rendering.
        let size = CGSize(width: 28, height: 28)
        let scale = max(size.width / image.size.width, size.height / image.size.height)
        let imageSize = CGSize(width: image.size.width * scale, height: image.size.height * scale)
        avatar = UIGraphicsImageRenderer(size: size).image { _ in
            UIBezierPath(ovalIn: CGRect(origin: .zero, size: size)).addClip()
            image.draw(in: CGRect(
                x: (size.width - imageSize.width) / 2,
                y: (size.height - imageSize.height) / 2,
                width: imageSize.width,
                height: imageSize.height
            ))
        }.withRenderingMode(.alwaysOriginal)
    }

}

#Preview {
    MainView(isLoggedIn: .constant(true))
}
