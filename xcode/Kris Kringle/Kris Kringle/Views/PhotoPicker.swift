import SwiftUI
import PhotosUI
import UIKit
import ImageIO
import UniformTypeIdentifiers

struct PhotoPicker: View {
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
                    .accessibilityLabel("Selected photo")
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
        .modifier(PhotoPreparation(
            selection: $selection, picture: $picture, isPreparing: $isPreparing, errorMessage: $errorMessage
        ))
    }
}

struct PhotoPreparation: ViewModifier {
    @Binding var selection: PhotosPickerItem?
    @Binding var picture: Data?
    @Binding var isPreparing: Bool
    @Binding var errorMessage: String?

    func body(content: Content) -> some View {
        content
            .onChange(of: selection) {
                isPreparing = selection != nil
                errorMessage = nil
            }
            .onChange(of: picture) {
                if picture == nil { selection = nil }
            }
            .task(id: selection) {
                guard let item = selection else { return }
                isPreparing = true
                defer { if !Task.isCancelled && item == selection { isPreparing = false } }
                do {
                    guard let data = try await item.loadTransferable(type: Data.self) else {
                        guard !Task.isCancelled, item == selection else { return }
                        errorMessage = "Couldn’t open that photo."
                        return
                    }
                    let jpeg = await Task.detached(priority: .userInitiated) { Self.makeJPEG(from: data) }.value
                    try Task.checkCancellation()
                    guard item == selection else { return }
                    guard let jpeg else {
                        errorMessage = "Couldn’t prepare that photo. Try a different one."
                        return
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
