//
//  Kris_KrigleApp.swift
//  Kris Krigle
//
//  Created by Lucas Sparke on 8/10/2026.
//

import SwiftUI
import CoreData

@main
struct Kris_KrigleApp: App {
    let persistenceController = PersistenceController.shared

    var body: some Scene {
        WindowGroup {
            ContentView()
                .environment(\.managedObjectContext, persistenceController.container.viewContext)
        }
    }
}
