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
    @Binding var profilePhoto: UIImage?
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
                let currentYear = Calendar.current.component(.year, from: Date())
                let highlights = groups.filter { $0.year == currentYear && ($0.recipientName != nil || !$0.childGiftRecipients.isEmpty) }
                List {
                    if !highlights.isEmpty {
                        Section {
                            ForEach(highlights) { group in
                                if let recipientName = group.recipientName {
                                    GiftRecipientCard(giverName: nil, recipientName: recipientName, krisKringleName: group.name)
                                }
                                ForEach(group.childGiftRecipients) { child in
                                    GiftRecipientCard(giverName: child.childName, recipientName: child.recipientName, krisKringleName: group.name)
                                }
                            }
                        }
                    }
                    Section {
                        ForEach(groups) { group in
                            NavigationLink {
                                KrisKringleDetailView(group: group, photos: photos, currentUserPhoto: $profilePhoto, onUpdate: upsertGroup)
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
                            .translucentDarkRow()
                        }
                    }
                }
                .listSectionSpacing(24)
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
            await loadGroups()
        }
        .sheet(isPresented: $isShowingCreate) {
            CreateKrisKringleView(previousKrisKringles: previousKrisKringles) { group, picture in
                photos.store(picture, for: group)
                upsertGroup(group)
            }
        }
    }

    private var previousKrisKringles: [KrisKringleSummary] {
        guard case .loaded(let groups) = state else { return [] }
        let year = Calendar.current.component(.year, from: Date())
        return groups.filter { $0.year < year }.sorted { ($0.year, $0.id) > ($1.year, $1.id) }
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

private struct GiftRecipientCard: View {
    let giverName: String?
    let recipientName: String
    let krisKringleName: String

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            Image(systemName: "gift.fill")
                .font(.largeTitle)
                .foregroundStyle(Theme.christmasAccent)
                .frame(maxWidth: .infinity)
                .accessibilityHidden(true)
            VStack(alignment: .leading, spacing: 4) {
                if let giverName {
                    Text("\(giverName) is buying for")
                        .fixedSize(horizontal: false, vertical: true)
                } else {
                    Text("You're buying for")
                        .lineLimit(1)
                        .minimumScaleFactor(0.75)
                }
                Text(recipientName)
                    .fixedSize(horizontal: false, vertical: true)
            }
            .font(.title.bold())
            .foregroundStyle(.white)
            .accessibilityElement(children: .combine)
            Text(krisKringleName)
                .font(.subheadline)
                .foregroundStyle(.white.opacity(0.8))
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(24)
        .background(
            Theme.cardBackground,
            in: RoundedRectangle(cornerRadius: 16)
        )
        .overlay {
            RoundedRectangle(cornerRadius: 16)
                .strokeBorder(Theme.christmasAccent.opacity(0.6), lineWidth: 1)
        }
        .listRowInsets(EdgeInsets(top: 12, leading: 0, bottom: 12, trailing: 0))
        .listRowBackground(Color.clear)
        .listRowSeparator(.hidden)
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
                    .accessibilityLabel(group.pictureVersion.isEmpty && draft == nil ? "Add Kris Kringle Photo" : "Change Kris Kringle Photo")
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
    @State private var firstGroupName = ""
    @State private var copyFromId = 0
    @State private var showCopyPrompt = false
    @State private var hasAskedToCopy = false
    @State private var picture: Data?
    @State private var isPreparingPhoto = false
    @State private var isSaving = false
    @State private var errorMessage: String?
    let previousKrisKringles: [KrisKringleSummary]
    let onSave: (KrisKringleSummary, Data?) -> Void

    private var trimmedName: String { name.trimmingCharacters(in: .whitespacesAndNewlines) }
    private var trimmedFirstGroupName: String { firstGroupName.trimmingCharacters(in: .whitespacesAndNewlines) }
    private var canSave: Bool {
        !isSaving && !isPreparingPhoto && (1...100).contains(trimmedName.unicodeScalars.count)
            && (copyFromId > 0 || (1...100).contains(trimmedFirstGroupName.unicodeScalars.count))
    }

    var body: some View {
        NavigationStack {
            Form {
                Section {
                    TextField("Name", text: $name, prompt: Text("Christmas Gift Exchange"))
                        .textInputAutocapitalization(.words)
                        .submitLabel(.done)
                        .onSubmit(save)
                        .disabled(isSaving)
                        .translucentDarkRow()
                } footer: {
                    Text("Give your gift exchange a name (up to 100 characters).")
                        .foregroundStyle(.white)
                        .shadow(color: .black.opacity(0.85), radius: 2)
                }
                if !previousKrisKringles.isEmpty {
                    Section {
                        Picker("Copy from", selection: $copyFromId) {
                            Text("Start fresh").tag(0)
                            ForEach(previousKrisKringles) { previous in
                                Text("\(previous.name) (\(String(previous.year)))").tag(previous.id)
                            }
                        }
                        .disabled(isSaving)
                        .translucentDarkRow()
                    } header: {
                        Text("Groups and Members")
                    } footer: {
                        Text("Copy groups, members and children from a previous year. Gift recipients won’t be copied.")
                            .foregroundStyle(.white)
                            .shadow(color: .black.opacity(0.85), radius: 2)
                    }
                }
                if copyFromId == 0 {
                    Section {
                        TextField("First group", text: $firstGroupName, prompt: Text("Group name"))
                            .textInputAutocapitalization(.words)
                            .submitLabel(.done)
                            .onSubmit(save)
                            .disabled(isSaving)
                            .translucentDarkRow()
                    } header: {
                        Text("First Group")
                    } footer: {
                        Text("You’ll be part of this group.")
                            .foregroundStyle(.white)
                            .shadow(color: .black.opacity(0.85), radius: 2)
                    }
                }
                Section("Kris Kringle Photo") {
                    PhotoPicker(picture: $picture, isPreparing: $isPreparingPhoto, isSaving: isSaving)
                        .translucentDarkRow()
                }
                if isSaving { ProgressView("Saving Kris Kringle…").translucentDarkRow() }
                if let errorMessage { Text(errorMessage).foregroundStyle(.red).translucentDarkRow() }
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
        .task {
            guard !hasAskedToCopy else { return }
            hasAskedToCopy = true
            showCopyPrompt = !previousKrisKringles.isEmpty
        }
        .alert("Copy from a previous year?", isPresented: $showCopyPrompt) {
            Button("Copy Previous Year") { copyFromId = previousKrisKringles.first?.id ?? 0 }
            Button("Start Fresh", role: .cancel) { copyFromId = 0 }
        } message: {
            Text("Reuse groups, members and children from a previous Kris Kringle, without gift assignments. You can choose which year on the next screen.")
        }
    }

    private func save() {
        guard canSave else { return }
        let selectedPicture = picture
        let exchangeName = trimmedName
        let firstGroup = trimmedFirstGroupName
        let sourceId = copyFromId > 0 ? copyFromId : nil
        isSaving = true
        errorMessage = nil
        Task {
            defer { isSaving = false }
            do {
                let result = try await KrisKringleService.create(
                    name: exchangeName, firstGroupName: firstGroup, picture: selectedPicture, copyFromId: sourceId
                )
                onSave(result, selectedPicture)
                dismiss()
            } catch {
                errorMessage = error.localizedDescription
            }
        }
    }
}
