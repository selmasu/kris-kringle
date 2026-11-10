import SwiftUI

struct KrisKringleDetailView: View {
    let onUpdate: (KrisKringleSummary) -> Void
    @State private var group: KrisKringleSummary
    @State private var name: String
    @State private var members: [KrisKringleMember]?
    @State private var email = ""
    @State private var picture: Data?
    @State private var isPreparingPhoto = false
    @State private var isSaving = false
    @State private var isAdding = false
    @State private var loadError: String?
    @State private var saveError: String?
    @State private var memberError: String?

    init(group: KrisKringleSummary, onUpdate: @escaping (KrisKringleSummary) -> Void) {
        self.onUpdate = onUpdate
        _group = State(initialValue: group)
        _name = State(initialValue: group.name)
    }

    private var isCreator: Bool { group.createdBy == Globals.userId }
    private var isBusy: Bool { isSaving || isAdding }
    private var trimmedName: String { name.trimmingCharacters(in: .whitespacesAndNewlines) }
    private var trimmedEmail: String { email.trimmingCharacters(in: .whitespacesAndNewlines) }
    private var canSave: Bool {
        !isBusy && !isPreparingPhoto && (1...100).contains(trimmedName.unicodeScalars.count)
            && (trimmedName != group.name || picture != nil)
    }
    private var canAdd: Bool { !isBusy && !trimmedEmail.isEmpty }

    var body: some View {
        Form {
            if picture == nil {
                Section {
                    KrisKringlePhoto(group: group, size: 110)
                        .frame(maxWidth: .infinity)
                        .listRowSeparator(.hidden)
                }
            }
            if let members {
                if isCreator {
                    detailsSection
                    addMemberSection
                }
                Section("Members") {
                    ForEach(members) { member in
                        memberRow(member)
                    }
                }
            } else if let loadError {
                Section {
                    Text(loadError)
                    Button("Try Again") { Task { await load() } }
                }
            } else {
                ProgressView("Loading Kris Kringle…")
            }
        }
        .navigationTitle(group.name)
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
    }

    private var detailsSection: some View {
        Section("Group Details") {
            TextField("Name", text: $name)
                .textInputAutocapitalization(.words)
            PhotoPicker(picture: $picture, isPreparing: $isPreparingPhoto, isSaving: isBusy)
            Button(isSaving ? "Saving…" : "Save Changes", action: save)
                .disabled(!canSave)
            if let saveError { Text(saveError).foregroundStyle(.red) }
        }
        .disabled(isBusy)
    }

    private var addMemberSection: some View {
        Section {
            TextField("Email address", text: $email)
                .textContentType(.emailAddress)
                .keyboardType(.emailAddress)
                .textInputAutocapitalization(.never)
                .autocorrectionDisabled()
                .submitLabel(.go)
                .onSubmit(addMember)
            Button(isAdding ? "Adding…" : "Add Member", action: addMember)
                .disabled(!canAdd)
            if let memberError { Text(memberError).foregroundStyle(.red) }
        } header: {
            Text("Add a Member")
        } footer: {
            Text("They’ll see this Kris Kringle when they sign in with this email address.")
        }
        .disabled(isBusy)
    }

    private func memberRow(_ member: KrisKringleMember) -> some View {
        HStack {
            VStack(alignment: .leading, spacing: 4) {
                Text(member.name.isEmpty ? (member.email ?? "Member") : member.name)
                if !member.name.isEmpty, let email = member.email, !email.isEmpty {
                    Text(email).font(.subheadline).foregroundStyle(.secondary)
                }
            }
            Spacer()
            if member.isCreator {
                Text("Creator").font(.subheadline).foregroundStyle(.secondary)
            }
        }
    }

    private func load() async {
        loadError = nil
        do {
            let detail = try await KrisKringleService.detail(id: group.id)
            try Task.checkCancellation()
            let summaryChanged = group.name != detail.group.name || group.pictureVersion != detail.group.pictureVersion
            group = detail.group
            name = group.name
            members = detail.members
            if summaryChanged { onUpdate(group) }
        } catch is CancellationError {
        } catch {
            loadError = error.localizedDescription
        }
    }

    private func save() {
        guard canSave else { return }
        let updatedName = trimmedName == group.name ? nil : trimmedName
        let selectedPicture = picture
        isSaving = true
        saveError = nil
        Task {
            defer { isSaving = false }
            do {
                group = try await KrisKringleService.update(id: group.id, name: updatedName, picture: selectedPicture)
                name = group.name
                picture = nil
                onUpdate(group)
            } catch {
                saveError = error.localizedDescription
            }
        }
    }

    private func addMember() {
        guard canAdd else { return }
        let memberEmail = trimmedEmail
        isAdding = true
        memberError = nil
        Task {
            defer { isAdding = false }
            do {
                members = try await KrisKringleService.addMember(id: group.id, email: memberEmail)
                email = ""
            } catch {
                memberError = error.localizedDescription
            }
        }
    }
}
