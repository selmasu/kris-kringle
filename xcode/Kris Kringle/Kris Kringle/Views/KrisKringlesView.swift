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
                    NavigationLink {
                        KrisKringleDetailView(group: group, onUpdate: upsertGroup)
                    } label: {
                        HStack(spacing: 12) {
                            KrisKringlePhoto(group: group)
                            VStack(alignment: .leading, spacing: 4) {
                                Text(group.name).font(.headline)
                                Text("\(group.year, format: .number.grouping(.never)) · \(group.createdBy == Globals.userId ? "Created by you" : "Invited")")
                                    .font(.subheadline)
                                    .foregroundStyle(.secondary)
                            }
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
            CreateKrisKringleView(onSave: upsertGroup)
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

struct KrisKringlePhoto: View {
    let group: KrisKringleSummary
    var size: CGFloat = 44
    @State private var image: UIImage?

    var body: some View {
        Group {
            if let image {
                Image(uiImage: image).resizable().scaledToFill()
            } else {
                Image(systemName: "gift").foregroundStyle(Theme.green)
            }
        }
        .frame(width: size, height: size)
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

private struct CreateKrisKringleView: View {
    @Environment(\.dismiss) private var dismiss
    @State private var name = ""
    @State private var picture: Data?
    @State private var isPreparingPhoto = false
    @State private var isSaving = false
    @State private var errorMessage: String?
    let onSave: (KrisKringleSummary) -> Void

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
                } footer: {
                    Text("Give your gift exchange a name (up to 100 characters).")
                }
                Section("Group Photo") {
                    PhotoPicker(picture: $picture, isPreparing: $isPreparingPhoto, isSaving: isSaving)
                }
                if isSaving { ProgressView("Saving Kris Kringle…") }
                if let errorMessage { Text(errorMessage).foregroundStyle(.red) }
            }
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
                onSave(result)
                dismiss()
            } catch {
                errorMessage = error.localizedDescription
            }
        }
    }
}
