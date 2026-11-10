import Foundation
import Alamofire
import SwiftyJSON

struct KrisKringleSummary: Identifiable {
    let id: Int
    let name: String
    let year: Int
    let createdBy: Int
    let pictureVersion: String

    init(json: JSON) throws {
        guard let id = json["krisKringleId"].int ?? Int(json["krisKringleId"].stringValue), id > 0,
              let name = json["name"].string,
              let year = json["year"].int ?? Int(json["year"].stringValue),
              let createdBy = json["createdBy"].int ?? Int(json["createdBy"].stringValue) else {
            throw KrisKringleService.ServiceError("The server returned an invalid Kris Kringle.")
        }
        self.id = id
        self.name = name.isEmpty ? "Kris Kringle" : name
        self.year = year
        self.createdBy = createdBy
        self.pictureVersion = json["pictureVersion"].stringValue
    }
}

struct KrisKringleMember: Identifiable {
    let id: Int
    let name: String
    let email: String?
    let isCreator: Bool

    init(json: JSON) throws {
        guard let id = json["userId"].int, id > 0,
              let name = json["name"].string, let isCreator = json["isCreator"].bool else {
            throw KrisKringleService.ServiceError("The server returned an invalid group member.")
        }
        self.id = id
        self.name = name
        self.email = json["email"].string
        self.isCreator = isCreator
    }
}

enum KrisKringleService {
    struct ServiceError: LocalizedError {
        let message: String
        init(_ message: String) { self.message = message }
        var errorDescription: String? { message }
    }

    static func list() async throws -> [KrisKringleSummary] {
        let result = try await request("getKrisKringles", parameters: [:])
        guard let groups = result["data"].array else {
            throw ServiceError("The server didn’t return a list of Kris Kringles.")
        }
        return try groups.map(KrisKringleSummary.init(json:))
    }

    static func create(name: String, picture: Data? = nil) async throws -> KrisKringleSummary {
        let result = try await request("createKrisKringle", parameters: ["name": name], picture: picture)
        return try KrisKringleSummary(json: result["data"])
    }

    static func detail(id: Int) async throws -> (group: KrisKringleSummary, members: [KrisKringleMember]) {
        let result = try await request("getKrisKringle", parameters: ["id": String(id)])
        return (try KrisKringleSummary(json: result["data"]), try members(from: result))
    }

    static func update(id: Int, name: String?, picture: Data?) async throws -> KrisKringleSummary {
        var fields = ["id": String(id)]
        fields["name"] = name
        let result = try await request("updateKrisKringle", parameters: fields, picture: picture)
        return try KrisKringleSummary(json: result["data"])
    }

    static func addMember(id: Int, email: String) async throws -> [KrisKringleMember] {
        let result = try await request("addKrisKringleMember", parameters: ["id": String(id), "email": email])
        return try members(from: result)
    }

    private static func members(from result: JSON) throws -> [KrisKringleMember] {
        guard let members = result["members"].array else {
            throw ServiceError("The server didn’t return a list of group members.")
        }
        return try members.map { try KrisKringleMember(json: $0) }
    }

    static func picture(id: Int) async throws -> Data {
        try await authenticatedRequest("getKrisKringlePicture", parameters: ["id": String(id)])
            .validate(statusCode: 200..<300)
            .validate(contentType: ["image/jpeg"])
            .serializingData().value
    }

    private static func authenticatedRequest(_ function: String, parameters: [String: String], picture: Data? = nil) throws -> DataRequest {
        let token = Globals.selmasuToken
        guard !token.isEmpty else { throw ServiceError("Please sign in again.") }

        let url = Utils.requestURL("krisKringles", service: "krisKringles", function: function)
        let fields = parameters.merging(["selmasuToken": token]) { _, token in token }
        if let picture {
            return AF.upload(multipartFormData: { form in
                for (name, value) in fields { form.append(Data(value.utf8), withName: name) }
                form.append(picture, withName: "picture", fileName: "group.jpg", mimeType: "image/jpeg")
            }, to: url, method: .post)
        }
        return AF.request(url, method: .post, parameters: fields)
    }

    private static func request(_ function: String, parameters: [String: String], picture: Data? = nil) async throws -> JSON {
        let request = try authenticatedRequest(function, parameters: parameters, picture: picture)
        let data: Data
        do {
            data = try await request.validate().serializingData().value
        } catch {
            try Task.checkCancellation()
            throw ServiceError("Couldn’t reach the server. Please try again.")
        }

        guard let result = try? JSON(data: data), result.dictionary != nil else {
            throw ServiceError("The server returned an unexpected response. Please try again.")
        }
        if let error = result["error"].string, !error.isEmpty {
            throw ServiceError(error)
        }
        return result
    }
}
