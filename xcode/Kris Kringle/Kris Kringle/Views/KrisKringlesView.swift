//
//  KrisKringlesView.swift
//  Kris Kringle
//
//  Created by Lucas Sparke on 8/10/2026.
//

import SwiftUI
import Foundation
import UIKit

struct KrisKringlesView: View {
    private enum ListState {
        case loading
        case loaded([KrisKringleSummary])
        case failed(String)
    }

    @State private var state: ListState = .loading
    @State private var isShowingCreate = false
    @State private var photos = KrisKringlePhotoCache()

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
                        .tint(Theme.green)
                }
            case .loaded(let groups):
                List(groups) { group in
                    NavigationLink {
                        KrisKringleDetailView(group: group, photos: photos, onUpdate: upsertGroup)
                    } label: {
                        HStack(spacing: 12) {
                            KrisKringlePhoto(group: group, cache: photos)
                            VStack(alignment: .leading, spacing: 4) {
                                Text(group.name).font(.headline)
                                Text("\(group.year, format: .number.grouping(.never)) · \(group.createdBy == Globals.userId ? "Created by you" : "Invited")")
                                    .font(.subheadline)
                                    .foregroundStyle(.secondary)
                            }
                        }
                    }
                    .christmasGlassRow()
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
                        .tint(Theme.green)
                }
            }
        }
        .christmasBackground()
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
            CreateKrisKringleView { group, picture in
                photos.store(picture, for: group)
                upsertGroup(group)
            }
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

final class KrisKringlePhotoCache {
    private let images = NSCache<NSString, UIImage>()

    init() {
        images.countLimit = 8
    }

    func image(for group: KrisKringleSummary) -> UIImage? {
        images.object(forKey: key(for: group))
    }

    @discardableResult
    func store(_ data: Data?, for group: KrisKringleSummary) -> UIImage? {
        guard !group.pictureVersion.isEmpty, let data, let image = UIImage(data: data) else { return nil }
        images.setObject(image, forKey: key(for: group))
        return image
    }

    private func key(for group: KrisKringleSummary) -> NSString {
        "\(group.id):\(group.pictureVersion)" as NSString
    }
}

struct KrisKringlePhoto: View {
    let group: KrisKringleSummary
    let cache: KrisKringlePhotoCache
    var width: CGFloat? = 44
    var height: CGFloat = 44
    var draft: UIImage?
    var onTap: (() -> Void)?
    var showLoadError = false
    @State private var image: UIImage?
    @State private var loadFailed = false
    @State private var retryCount = 0

    var body: some View {
        VStack(spacing: 6) {
            if let onTap {
                Button(action: onTap) { photo }
                    .buttonStyle(.plain)
                    .accessibilityLabel(group.pictureVersion.isEmpty && draft == nil ? "Add Group Photo" : "Change Group Photo")
            } else {
                photo
            }

            if showLoadError && loadFailed && draft == nil {
                Button("Couldn’t Load Photo. Retry") { retryCount += 1 }
                    .buttonStyle(.borderless)
                    .font(.footnote)
            }
        }
        .task(id: [String(group.id), group.pictureVersion, String(retryCount)]) {
            await loadPhoto()
        }
    }

    private var photo: some View {
        Theme.green.opacity(0.1)
            .frame(width: width, height: height)
            .overlay {
                if let displayedImage = draft ?? image {
                    Image(uiImage: displayedImage).resizable().scaledToFill()
                } else {
                    Image(systemName: "gift").foregroundStyle(.tint)
                }
            }
            .clipShape(RoundedRectangle(cornerRadius: 9))
            .contentShape(Rectangle())
            .accessibilityHidden(true)
    }

    private func loadPhoto() async {
        loadFailed = false
        image = cache.image(for: group)
        guard image == nil, !group.pictureVersion.isEmpty else { return }

        do {
            let data = try await KrisKringleService.picture(id: group.id)
            try Task.checkCancellation()
            image = cache.store(data, for: group)
            loadFailed = image == nil
        } catch {
            if !Task.isCancelled { loadFailed = true }
        }
    }
}

private struct CreateKrisKringleView: View {
    @Environment(\.dismiss) private var dismiss
    @State private var name = ""
    @State private var picture: Data?
    @State private var isPreparingPhoto = false
    @State private var isSaving = false
    @State private var errorMessage: String?
    let onSave: (KrisKringleSummary, Data?) -> Void

    private var trimmedName: String { name.trimmingCharacters(in: .whitespacesAndNewlines) }
    private var canSave: Bool {
        !isSaving && !isPreparingPhoto && (1...100).contains(trimmedName.unicodeScalars.count)
    }

    var body: some View {
        NavigationStack {
            Form {
                Section {
                    TextField("Name", text: $name, prompt: Text("Family Christmas"))
                        .textInputAutocapitalization(.words)
                        .submitLabel(.done)
                        .onSubmit(save)
                        .disabled(isSaving)
                        .christmasGlassRow()
                } footer: {
                    Text("Give your gift exchange a name (up to 100 characters).")
                        .foregroundStyle(.white)
                        .shadow(color: .black.opacity(0.85), radius: 2)
                }
                Section("Group Photo") {
                    PhotoPicker(picture: $picture, isPreparing: $isPreparingPhoto, isSaving: isSaving)
                        .christmasGlassRow()
                }
                if isSaving { ProgressView("Saving Kris Kringle…").christmasGlassRow() }
                if let errorMessage { Text(errorMessage).foregroundStyle(.red).christmasGlassRow() }
            }
            .christmasBackground()
            .navigationTitle("Create Kris Kringle")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { dismiss() }.disabled(isSaving)
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button("Create", action: save).disabled(!canSave)
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
                let result = try await KrisKringleService.create(name: groupName, picture: selectedPicture)
                onSave(result, selectedPicture)
                dismiss()
            } catch {
                errorMessage = error.localizedDescription
            }
        }
    }
}
