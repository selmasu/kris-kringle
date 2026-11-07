//
//  NetworkMonitor.swift
//  Kris Kringle
//
//  Created by Lucas Sparke on 5/11/2026.
//

import Foundation
import Network
import Combine

class NetworkMonitor: ObservableObject {
    @Published var isConnected = false
    private let networkMonitor = NWPathMonitor()
    private let workerQueue = DispatchQueue(label: "Monitor")

    init() {
        networkMonitor.pathUpdateHandler = { path in
            self.isConnected = path.status == .satisfied
        }
        networkMonitor.start(queue: workerQueue)
    }
}
