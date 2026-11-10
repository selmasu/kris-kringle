import Alamofire
import Foundation

struct MyProfile: Decodable {
    var name: String
    var picture: Data?
    var appleLinked: Bool
}

enum ProfileService {
    private struct Response: Decodable {
        var error: String?
        var saved: Bool?
        var appleLinked: Bool?
    }

    private struct ServiceError: LocalizedError {
        let message: String
        var errorDescription: String? { message }
    }

    static func load() async throws -> MyProfile {
        try await request("getMyProfile")
    }

    static func save(name: String?, picture: Data?) async throws {
        let fields = name.map { ["name": $0] } ?? [:]
        let response: Response = try await request("updateMyProfile", fields: fields, picture: picture)
        guard response.saved == true else {
            throw ServiceError(message: "The server couldn’t save your profile. Please try again.")
        }
    }

    static func linkApple(code: String, salt: String) async throws {
        let response: Response = try await request(
            "linkAppleAccount", app: "framework", service: "authentication",
            fields: ["code": code, "salt": salt]
        )
        guard response.appleLinked == true else {
            throw ServiceError(message: "The server couldn’t link your Apple account. Please try again.")
        }
    }

    private static func request<T: Decodable>(
        _ function: String, app: String = "users", service: String = "users",
        fields: [String: String] = [:], picture: Data? = nil
    ) async throws -> T {
        let token = Globals.selmasuToken
        guard !token.isEmpty else { throw ServiceError(message: "Please sign in again.") }

        let url = Utils.requestURL(app, service: service, function: function)
        let parameters = fields.merging(["selmasuToken": token]) { _, token in token }
        let request: DataRequest
        if let picture {
            request = AF.upload(multipartFormData: { form in
                for (name, value) in parameters { form.append(Data(value.utf8), withName: name) }
                form.append(picture, withName: "picture", fileName: "profile.jpg", mimeType: "image/jpeg")
            }, to: url, method: .post)
        } else {
            request = AF.request(url, method: .post, parameters: parameters)
        }

        let data: Data
        do {
            data = try await request.validate().serializingData().value
        } catch {
            try Task.checkCancellation()
            throw ServiceError(message: "Couldn’t reach the server. Please try again.")
        }
        try Task.checkCancellation()
        guard token == Globals.selmasuToken else { throw CancellationError() }

        let decoder = JSONDecoder()
        if let response = try? decoder.decode(Response.self, from: data), let error = response.error, !error.isEmpty {
            throw ServiceError(message: error)
        }
        guard let response = try? decoder.decode(T.self, from: data) else {
            throw ServiceError(message: "The server returned an unexpected response. Please try again.")
        }
        return response
    }
}
