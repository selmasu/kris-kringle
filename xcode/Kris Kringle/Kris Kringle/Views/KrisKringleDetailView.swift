import ContactsUI
import PhotosUI
import SwiftUI
import UIKit

struct KrisKringleDetailView: View {
    let onUpdate: (KrisKringleSummary) -> Void
    let photos: KrisKringlePhotoCache
    @State private var group: KrisKringleSummary
    @State private var name: String
    @State private var members: [KrisKringleMember]?
    @State private var parentForChild: KrisKringleMember?
    @State private var email = ""
    @State private var isChoosingContact = false
    @State private var contactEmails: [String] = []
    @State private var picture: Data?
    @State private var isChoosingPhoto = false
    @State private var photoSelection: PhotosPickerItem?
    @State private var isPreparingPhoto = false
    @State private var photoError: String?
    @State private var isSaving = false
    @State private var isAdding = false
    @State private var loadError: String?
    @State private var saveError: String?
    @State private var memberError: String?

    init(group: KrisKringleSummary, photos: KrisKringlePhotoCache, onUpdate: @escaping (KrisKringleSummary) -> Void) {
        self.onUpdate = onUpdate
        self.photos = photos
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
    private var canAdd: Bool { !isBusy && contactEmails.isEmpty && !trimmedEmail.isEmpty }

    var body: some View {
        Form {
            photoSection
            if let members {
                if isCreator {
                    detailsSection
                    addMemberSection
                }
                Section("Members") {
                    ForEach(members) { member in
                        memberRow(member)
                        ForEach(member.children) { child in
                            Label(child.name, systemImage: "arrow.turn.down.right")
                                .padding(.leading, 20)
                                .accessibilityLabel("\(child.name), child of \(member.displayName)")
                        }
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
        .photosPicker(isPresented: $isChoosingPhoto, selection: $photoSelection, matching: .images)
        .modifier(PhotoPreparation(
            selection: $photoSelection, picture: $picture, isPreparing: $isPreparingPhoto, errorMessage: $photoError
        ))
        .christmasBackground()
        .navigationTitle(group.name)
        .navigationBarTitleDisplayMode(.inline)
        .task {
            if members == nil { await load() }
        }
        .sheet(item: $parentForChild) { parent in
            AddKrisKringleChildView(groupId: group.id, parent: parent) { members = $0 }
        }
        .background {
            ContactEmailPicker(isPresented: $isChoosingContact, onFinish: selectContact)
                .frame(width: 0, height: 0)
        }
    }

    private var photoSection: some View {
        let draft = picture.flatMap { UIImage(data: $0) }
        let canChoosePhoto = isCreator && members != nil
        return Section {
            KrisKringlePhoto(
                group: group, cache: photos, width: nil, height: 220, draft: draft,
                onTap: canChoosePhoto ? { isChoosingPhoto = true } : nil, showLoadError: true
            )
            .disabled(canChoosePhoto && (isBusy || isPreparingPhoto))
            .contextMenu {
                if picture != nil && !isBusy {
                    Button("Discard Photo", systemImage: "xmark", role: .destructive) {
                        picture = nil
                        photoError = nil
                    }
                }
            }
            .listRowInsets(EdgeInsets())
            .listRowBackground(Color.clear)
            .listRowSeparator(.hidden)
            if isPreparingPhoto { ProgressView("Preparing photo…") }
            if let photoError { Text(photoError).foregroundStyle(.red) }
        }
    }

    private var detailsSection: some View {
        Section("Group Details") {
            HStack(spacing: 8) {
                TextField("Name", text: $name)
                    .textInputAutocapitalization(.words)
                    .submitLabel(.done)
                    .onSubmit(save)
                Button(action: save) {
                    Group {
                        if isSaving {
                            ProgressView()
                        } else {
                            Image(systemName: "checkmark.circle.fill")
                                .font(.title3)
                        }
                    }
                    .frame(width: 44, height: 44)
                }
                .buttonStyle(.borderless)
                .accessibilityLabel("Save Group Changes")
                .disabled(!canSave)
            }
            if let saveError { Text(saveError).foregroundStyle(.red) }
        }
        .disabled(isBusy)
    }

    private var addMemberSection: some View {
        Section {
            if contactEmails.isEmpty {
                HStack(spacing: 8) {
                    TextField("Email address", text: $email)
                        .textContentType(.emailAddress)
                        .keyboardType(.emailAddress)
                        .textInputAutocapitalization(.never)
                        .autocorrectionDisabled()
                        .submitLabel(.go)
                        .onSubmit(addMember)
                    Button(action: addMember) {
                        Group {
                            if isAdding {
                                ProgressView()
                            } else {
                                Image(systemName: "plus.circle.fill")
                                    .font(.title3)
                            }
                        }
                        .frame(width: 44, height: 44)
                    }
                    .buttonStyle(.borderless)
                    .accessibilityLabel("Add Member")
                    .disabled(!canAdd)
                }
            } else {
                Text("Choose an email address").foregroundStyle(.secondary)
                ForEach(contactEmails, id: \.self) { address in
                    Button(address) {
                        email = address
                        contactEmails = []
                    }
                }
                Button("Cancel Email Selection") { contactEmails = [] }
            }
            if let memberError { Text(memberError).foregroundStyle(.red) }
        } header: {
            HStack {
                Text("Add a Member")
                Spacer()
                Button {
                    isChoosingContact = true
                } label: {
                    Image(systemName: "person.crop.rectangle")
                        .font(.title3)
                        .frame(minWidth: 44, minHeight: 44, alignment: .trailing)
                        .contentShape(Rectangle())
                }
                .buttonStyle(.borderless)
                .accessibilityLabel("Choose from Contacts")
            }
        } footer: {
            Text("They’ll see this Kris Kringle when they sign in with this email address.")
                .foregroundStyle(.white)
                .shadow(color: .black.opacity(0.85), radius: 2)
        }
        .disabled(isBusy)
    }

    private func memberRow(_ member: KrisKringleMember) -> some View {
        HStack {
            VStack(alignment: .leading, spacing: 4) {
                Text(member.displayName)
                if !member.name.isEmpty, let email = member.email, !email.isEmpty {
                    Text(email).font(.subheadline).foregroundStyle(.secondary)
                }
            }
            Spacer()
            if member.isCreator {
                Text("Creator").font(.subheadline).foregroundStyle(.secondary)
            }
            if isCreator {
                Button {
                    parentForChild = member
                } label: {
                    Image(systemName: "person.badge.plus")
                        .frame(width: 44, height: 44)
                }
                .buttonStyle(.borderless)
                .accessibilityLabel("Add Child under \(member.displayName)")
                .disabled(isBusy)
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
                let updatedGroup = try await KrisKringleService.update(id: group.id, name: updatedName, picture: selectedPicture)
                photos.store(selectedPicture, for: updatedGroup)
                group = updatedGroup
                name = group.name
                picture = nil
                onUpdate(group)
            } catch {
                saveError = error.localizedDescription
            }
        }
    }

    private func selectContact(_ addresses: [String]?) {
        isChoosingContact = false
        guard let addresses else { return }
        contactEmails = []
        memberError = nil
        switch addresses.count {
        case 0:
            memberError = "This contact doesn’t have an email address."
        case 1:
            email = addresses[0]
        default:
            contactEmails = addresses
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

private struct AddKrisKringleChildView: View {
    let groupId: Int
    let parent: KrisKringleMember
    let onAdd: ([KrisKringleMember]) -> Void
    @Environment(\.dismiss) private var dismiss
    @State private var name = ""
    @State private var isAdding = false
    @State private var errorMessage: String?

    private var trimmedName: String { name.trimmingCharacters(in: .whitespacesAndNewlines) }
    private var canAdd: Bool { !isAdding && (1...100).contains(trimmedName.unicodeScalars.count) }

    var body: some View {
        NavigationStack {
            Form {
                LabeledContent("Parent", value: parent.displayName)
                TextField("Child’s name", text: $name)
                    .textContentType(.name)
                    .textInputAutocapitalization(.words)
                    .submitLabel(.done)
                    .onSubmit(addChild)
                    .disabled(isAdding)
                if let errorMessage {
                    Text(errorMessage).foregroundStyle(.red)
                }
            }
            .christmasBackground()
            .navigationTitle("Add Child")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { dismiss() }.disabled(isAdding)
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button(isAdding ? "Adding…" : "Add", action: addChild).disabled(!canAdd)
                }
            }
        }
        .interactiveDismissDisabled(isAdding)
    }

    private func addChild() {
        guard canAdd else { return }
        let childName = trimmedName
        isAdding = true
        errorMessage = nil
        Task {
            defer { isAdding = false }
            do {
                let members = try await KrisKringleService.addChild(id: groupId, parentUserId: parent.id, name: childName)
                onAdd(members)
                dismiss()
            } catch {
                errorMessage = error.localizedDescription
            }
        }
    }
}

private struct ContactEmailPicker: UIViewControllerRepresentable {
    @Binding var isPresented: Bool
    let onFinish: ([String]?) -> Void

    func makeUIViewController(context: Context) -> UIViewController {
        UIViewController()
    }

    func updateUIViewController(_ host: UIViewController, context: Context) {
        guard isPresented else { return }
        let coordinator = context.coordinator
        Task { @MainActor in
            guard isPresented, host.viewIfLoaded?.window != nil, host.presentedViewController == nil else { return }
            let picker = CNContactPickerViewController()
            picker.delegate = coordinator
            picker.displayedPropertyKeys = [CNContactEmailAddressesKey]
            picker.predicateForEnablingContact = NSPredicate(format: "emailAddresses.@count > 0")
            picker.predicateForSelectionOfContact = NSPredicate(value: true)
            host.present(picker, animated: true)
        }
    }

    func makeCoordinator() -> Coordinator { Coordinator(onFinish: onFinish) }

    final class Coordinator: NSObject, CNContactPickerDelegate {
        let onFinish: ([String]?) -> Void

        init(onFinish: @escaping ([String]?) -> Void) {
            self.onFinish = onFinish
        }

        func contactPickerDidCancel(_ picker: CNContactPickerViewController) {
            onFinish(nil)
        }

        func contactPicker(_ picker: CNContactPickerViewController, didSelect contact: CNContact) {
            guard contact.isKeyAvailable(CNContactEmailAddressesKey) else {
                onFinish([])
                return
            }
            var seen = Set<String>()
            let addresses = contact.emailAddresses
                .map { ($0.value as String).trimmingCharacters(in: .whitespacesAndNewlines) }
                .filter { !$0.isEmpty && seen.insert($0.lowercased()).inserted }
            onFinish(addresses)
        }
    }
}
