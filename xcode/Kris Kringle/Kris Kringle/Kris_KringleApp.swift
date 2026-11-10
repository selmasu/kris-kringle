//
//  Kris_KringleApp.swift
//  Kris Kringle
//
//  Created by Lucas Sparke on 8/10/2026.
//

import SwiftUI
import GoogleSignIn

@main
struct Kris_KringleApp: App {
    @StateObject var networkMonitor = NetworkMonitor()

    var body: some Scene {
        WindowGroup {
            StartView()
                .onOpenURL { url in
                    GIDSignIn.sharedInstance.handle(url)
                }
                .environmentObject(networkMonitor)
        }
    }
}
