//
//  KrisKringlesView.swift
//  Kris Kringle
//
//  Created by Lucas Sparke on 8/10/2026.
//

import SwiftUI
import Foundation
import PhotosUI
import UIKit
import ImageIO
import UniformTypeIdentifiers

struct KrisKringlesView: View {
    private enum ListState {
        case loading
        case loaded([KrisKringleSummary])
        case failed(String)
    }

    @State private var state: ListState = .loading
    @State private var isShowingCreate = false
    @State private var groupForPhoto: KrisKringleSummary?

    var body: some View {
        Group {
            switch state {
            case .loading:
                ProgressView("Loading Kris Kringles…")
            case .loaded(let groups) where groups.isEmpty:
                ContentUnavailableView {
                    Label("No Kris Kringles Yet", systemImage: "gift")
                } description: {
                    Text("You haven’t created a Kris Kringle or been invited to one yet.")
                } actions: {
                    Button("Create Kris Kringle") { isShowingCreate = true }
                        .buttonStyle(.borderedProminent)
                }
            case .loaded(let groups):
                List(groups) { group in
                    HStack(spacing: 12) {
                        GroupThumbnail(group: group)
                        VStack(alignment: .leading, spacing: 4) {
                            Text(group.name).font(.headline)
                            Text("\(group.year, format: .number.grouping(.never)) · \(group.createdBy == Globals.userId ? "Created by you" : "Invited")")
                                .font(.subheadline)
                                .foregroundStyle(.secondary)
                        }
                        Spacer()
                        if group.createdBy == Globals.userId {
                            Button { groupForPhoto = group } label: {
                                Image(systemName: group.pictureVersion.isEmpty ? "photo.badge.plus" : "photo")
                                    .frame(width: 44, height: 44)
                            }
                            .buttonStyle(.borderless)
                            .accessibilityLabel(group.pictureVersion.isEmpty ? "Add Group Photo" : "Change Group Photo")
                        }
                    }
                }
                .refreshable { await loadGroups() }
            case .failed(let message):
                ContentUnavailableView {
                    Label("Couldn’t Load Kris Kringles", systemImage: "exclamationmark.circle")
                } description: {
                    Text(message)
                } actions: {
                    Button("Try Again") {
                        state = .loading
                        Task { await loadGroups() }
                    }
                    .buttonStyle(.borderedProminent)
                }
            }
        }
        .navigationTitle("Kris Kringles")
        .toolbar {
            if case .loaded(let groups) = state, !groups.isEmpty {
                ToolbarItem(placement: .primaryAction) {
                    Button { isShowingCreate = true } label: {
                        Label("Create Kris Kringle", systemImage: "plus")
                    }
                }
            }
        }
        .task {
            if case .loading = state { await loadGroups() }
        }
        .sheet(isPresented: $isShowingCreate) {
            KrisKringleForm(group: nil, onSave: upsertGroup)
        }
        .sheet(item: $groupForPhoto) { group in
            KrisKringleForm(group: group, onSave: upsertGroup)
        }
    }

    private func upsertGroup(_ group: KrisKringleSummary) {
        let groups: [KrisKringleSummary]
        if case .loaded(let existing) = state {
            groups = existing.filter { $0.id != group.id }
        } else {
            groups = []
        }
        state = .loaded((groups + [group]).sorted { ($0.year, $0.id) > ($1.year, $1.id) })
    }

    private func loadGroups() async {
        do {
            let groups = try await KrisKringleService.list()
            try Task.checkCancellation()
            state = .loaded(groups)
        } catch is CancellationError {
            return
        } catch {
            state = .failed(error.localizedDescription)
        }
    }
}

private struct GroupThumbnail: View {
    let group: KrisKringleSummary
    @State private var image: UIImage?

    var body: some View {
        Group {
            if let image {
                Image(uiImage: image).resizable().scaledToFill()
            } else {
                Image(systemName: "gift").foregroundStyle(Theme.green)
            }
        }
        .frame(width: 44, height: 44)
        .clipShape(RoundedRectangle(cornerRadius: 9))
        .accessibilityHidden(true)
        .task(id: group.pictureVersion) {
            image = nil
            guard !group.pictureVersion.isEmpty,
                  let data = try? await KrisKringleService.picture(id: group.id),
                  !Task.isCancelled else { return }
            image = UIImage(data: data)
        }
    }
}

private struct GroupPhotoPicker: View {
    @Binding var picture: Data?
    @Binding var isPreparing: Bool
    let isSaving: Bool
    @State private var selection: PhotosPickerItem?
    @State private var errorMessage: String?

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            if let picture, let image = UIImage(data: picture) {
                Image(uiImage: image)
                    .resizable()
                    .scaledToFit()
                    .frame(maxHeight: 180)
                    .clipShape(RoundedRectangle(cornerRadius: 10))
                    .accessibilityLabel("Selected group photo")
            }
            PhotosPicker(selection: $selection, matching: .images) {
                Label(picture == nil ? "Choose Photo" : "Choose Another Photo", systemImage: "photo")
            }
            .disabled(isSaving)
            if isPreparing { ProgressView("Preparing photo…") }
            if let errorMessage { Text(errorMessage).foregroundStyle(.red) }
            if picture != nil {
                Button("Clear Selection") {
                    selection = nil
                    picture = nil
                    isPreparing = false
                }
                .disabled(isSaving)
            }
        }
        .onChange(of: selection) {
            isPreparing = selection != nil
            errorMessage = nil
        }
        .task(id: selection) {
            guard let item = selection else { return }
            isPreparing = true
            defer { if !Task.isCancelled && item == selection { isPreparing = false } }
            do {
                guard let data = try await item.loadTransferable(type: Data.self) else {
                    throw KrisKringleService.ServiceError("Couldn’t open that photo.")
                }
                let jpeg = await Task.detached(priority: .userInitiated) { Self.makeJPEG(from: data) }.value
                try Task.checkCancellation()
                guard item == selection else { return }
                guard let jpeg else {
                    throw KrisKringleService.ServiceError("Couldn’t prepare that photo. Try a different one.")
                }
                picture = jpeg
            } catch {
                guard !Task.isCancelled, item == selection else { return }
                errorMessage = error.localizedDescription
            }
        }
    }

    private nonisolated static func makeJPEG(from data: Data) -> Data? {
        guard let source = CGImageSourceCreateWithData(data as CFData, [kCGImageSourceShouldCache: false] as CFDictionary),
              let thumbnail = CGImageSourceCreateThumbnailAtIndex(source, 0, [
                kCGImageSourceCreateThumbnailFromImageAlways: true,
                kCGImageSourceCreateThumbnailWithTransform: true,
                kCGImageSourceThumbnailMaxPixelSize: 1024
              ] as CFDictionary) else { return nil }

        for quality in [0.84, 0.65, 0.45, 0.30] {
            let output = NSMutableData()
            guard let encoder = CGImageDestinationCreateWithData(output, UTType.jpeg.identifier as CFString, 1, nil) else { return nil }
            CGImageDestinationAddImage(encoder, thumbnail, [kCGImageDestinationLossyCompressionQuality: quality] as CFDictionary)
            if CGImageDestinationFinalize(encoder), output.length <= 512 * 1024 { return output as Data }
        }
        return nil
    }
}

private struct KrisKringleForm: View {
    @Environment(\.dismiss) private var dismiss
    @State private var name = ""
    @State private var picture: Data?
    @State private var isPreparingPhoto = false
    @State private var isSaving = false
    @State private var errorMessage: String?
    let group: KrisKringleSummary?
    let onSave: (KrisKringleSummary) -> Void

    private var trimmedName: String { name.trimmingCharacters(in: .whitespacesAndNewlines) }
    private var canSave: Bool {
        !isSaving && !isPreparingPhoto && (group == nil ? (1...100).contains(trimmedName.unicodeScalars.count) : picture != nil)
    }

    var body: some View {
        NavigationStack {
            Form {
                if group == nil {
                    Section {
                        TextField("Name", text: $name, prompt: Text("Family Christmas"))
                            .textInputAutocapitalization(.words)
                            .submitLabel(.done)
                            .onSubmit(save)
                            .disabled(isSaving)
                    } footer: {
                        Text("Give your gift exchange a name (up to 100 characters).")
                    }
                }
                Section("Group Photo") {
                    GroupPhotoPicker(picture: $picture, isPreparing: $isPreparingPhoto, isSaving: isSaving)
                }
                if isSaving { ProgressView("Saving Kris Kringle…") }
                if let errorMessage { Text(errorMessage).foregroundStyle(.red) }
            }
            .navigationTitle(group == nil ? "Create Kris Kringle" : "Group Photo")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { dismiss() }.disabled(isSaving)
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button(group == nil ? "Create" : "Save", action: save).disabled(!canSave)
                }
            }
        }
        .interactiveDismissDisabled(isSaving)
    }

    private func save() {
        guard canSave else { return }
        let selectedPicture = picture
        let groupName = trimmedName
        isSaving = true
        errorMessage = nil
        Task {
            defer { isSaving = false }
            do {
                let result: KrisKringleSummary
                if let group, let selectedPicture {
                    result = try await KrisKringleService.updatePicture(id: group.id, picture: selectedPicture)
                } else {
                    result = try await KrisKringleService.create(name: groupName, picture: selectedPicture)
                }
                onSave(result)
                dismiss()
            } catch {
                errorMessage = error.localizedDescription
            }
        }
    }
}
