import SwiftUI

extension View {
    func christmasBackground() -> some View {
        self
            .frame(maxWidth: .infinity, maxHeight: .infinity)
            .scrollContentBackground(.hidden)
            .background {
                GeometryReader { geometry in
                    Image(.christmasBackground)
                        .resizable()
                        .scaledToFill()
                        .frame(width: geometry.size.width, height: geometry.size.height)
                        .clipped()
                        .overlay(.black.opacity(0.15))
                }
                .ignoresSafeArea()
                .accessibilityHidden(true)
                .allowsHitTesting(false)
            }
            .environment(\.colorScheme, .dark)
            .tint(Theme.christmasAccent)
    }

    func christmasGlassRow() -> some View {
        self
            .environment(\.colorScheme, .light)
            .tint(Theme.green)
            .listItemTint(Theme.green)
            .listRowBackground(Color.white.opacity(0.8))
    }
}
