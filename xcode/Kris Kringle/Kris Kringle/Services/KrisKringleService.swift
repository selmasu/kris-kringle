import Foundation
import Alamofire
import SwiftyJSON

struct KrisKringleSummary: Identifiable {
    let id: Int
    let name: String
    let year: Int
    let createdBy: Int
    let isSecret: Bool
    let pictureVersion: String
    let recipientName: String?
    let childGiftRecipients: [KrisKringleChildGiftRecipient]

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
        self.isSecret = json["secret"].bool ?? ((json["secret"].int ?? 0) != 0)
        self.pictureVersion = json["pictureVersion"].stringValue
        self.recipientName = json["recipientName"].string
        self.childGiftRecipients = try (json["childGiftRecipients"].array ?? []).map(KrisKringleChildGiftRecipient.init(json:))
    }
}

struct KrisKringleChildGiftRecipient: Identifiable {
    let id: Int
    let childName: String
    let recipientName: String

    init(json: JSON) throws {
        guard let id = json["memberId"].int, id > 0,
              let childName = json["childName"].string,
              let recipientName = json["recipientName"].string else {
            throw KrisKringleService.ServiceError("The server returned an invalid child gift assignment.")
        }
        self.id = id
        self.childName = childName
        self.recipientName = recipientName
    }
}

struct KrisKringleGroup: Identifiable {
    let id: Int
    let name: String

    init(json: JSON) throws {
        guard let id = json["groupId"].int, id > 0, let name = json["name"].string else {
            throw KrisKringleService.ServiceError("The server returned an invalid group.")
        }
        self.id = id
        self.name = name
    }
}

struct KrisKringleMember: Identifiable {
    struct Child: Identifiable {
        let id: Int
        let name: String

        init(json: JSON) throws {
            guard let id = json["memberId"].int, id > 0, let name = json["name"].string else {
                throw KrisKringleService.ServiceError("The server returned an invalid child member.")
            }
            self.id = id
            self.name = name
        }
    }

    let id: Int
    let memberId: Int?
    let groupId: Int
    let name: String
    let email: String?
    let pictureVersion: String
    let pictureURL: URL?
    let isCreator: Bool
    let children: [Child]

    var displayName: String { name.isEmpty ? (email ?? "Member") : name }
    var hasPicture: Bool { !pictureVersion.isEmpty || pictureURL != nil }

    init(json: JSON) throws {
        guard let id = json["userId"].int, id > 0,
              let groupId = json["groupId"].int, groupId > 0,
              let name = json["name"].string, let isCreator = json["isCreator"].bool else {
            throw KrisKringleService.ServiceError("The server returned an invalid group member.")
        }
        self.id = id
        let memberId = json["memberId"].int ?? Int(json["memberId"].stringValue)
        self.memberId = memberId.flatMap { $0 > 0 ? $0 : nil }
        self.name = name
        self.groupId = groupId
        self.email = json["email"].string
        self.pictureVersion = json["pictureVersion"].stringValue
        let pictureURL = URL(string: json["pictureURL"].stringValue)
        self.pictureURL = ["https", "http"].contains(pictureURL?.scheme?.lowercased() ?? "") ? pictureURL : nil
        self.isCreator = isCreator
        self.children = try (json["children"].array ?? []).map { try Child(json: $0) }
    }
}

enum KrisKringleGiftGiverID: Hashable {
    case member(Int)
    case user(Int)

    fileprivate var parameter: (name: String, value: String) {
        switch self {
        case .member(let id): ("giverId", String(id))
        case .user(let id): ("giverUserId", String(id))
        }
    }
}

struct KrisKringleGiftAssignment {
    let giverId: Int
    let recipientName: String

    init(json: JSON) throws {
        guard let giverId = json["giverId"].int, let recipientName = json["recipientName"].string else {
            throw KrisKringleService.ServiceError("The server returned an invalid gift assignment.")
        }
        self.giverId = giverId
        self.recipientName = recipientName
    }
}

struct KrisKringleGiftRecipient: Identifiable {
    let id: Int
    let groupId: Int
    let name: String

    init(json: JSON) throws {
        guard let id = json["memberId"].int, let groupId = json["groupId"].int,
              let name = json["name"].string else {
            throw KrisKringleService.ServiceError("The server returned an invalid gift recipient.")
        }
        self.id = id
        self.groupId = groupId
        self.name = name
    }
}

struct KrisKringleGiftOptions {
    let recipients: [KrisKringleGiftRecipient]
    let recipientId: Int?
    let recipientName: String?

    init(json: JSON) throws {
        guard let options = json["options"].array else {
            throw KrisKringleService.ServiceError("The server didn’t return gift recipient options.")
        }
        recipients = try options.map { try KrisKringleGiftRecipient(json: $0) }
        recipientId = json["recipientId"].int
        recipientName = json["recipientName"].string
    }
}

struct KrisKringleRoster {
    let groups: [KrisKringleGroup]
    let members: [KrisKringleMember]
    let assignments: [Int: KrisKringleGiftAssignment]

    init(json: JSON) throws {
        guard let groups = json["groups"].array, let members = json["members"].array else {
            throw KrisKringleService.ServiceError("The server didn’t return the Kris Kringle roster.")
        }
        self.groups = try groups.map { try KrisKringleGroup(json: $0) }
        self.members = try members.map { try KrisKringleMember(json: $0) }
        let assignments = try (json["assignments"].array ?? []).map { try KrisKringleGiftAssignment(json: $0) }
        self.assignments = Dictionary(assignments.map { ($0.giverId, $0) }, uniquingKeysWith: { first, _ in first })
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

    static func create(name: String, firstGroupName: String, picture: Data? = nil, copyFromId: Int? = nil, isSecret: Bool = true) async throws -> KrisKringleSummary {
        var fields = ["name": name, "firstGroupName": firstGroupName, "secret": isSecret ? "1" : "0"]
        if let copyFromId { fields["copyFromId"] = String(copyFromId) }
        let result = try await request("createKrisKringle", parameters: fields, picture: picture)
        return try KrisKringleSummary(json: result["data"])
    }

    static func detail(id: Int) async throws -> (group: KrisKringleSummary, roster: KrisKringleRoster) {
        let result = try await request("getKrisKringle", parameters: ["id": String(id)])
        return (try KrisKringleSummary(json: result["data"]), try KrisKringleRoster(json: result))
    }

    static func update(id: Int, name: String?, picture: Data?, isSecret: Bool? = nil) async throws -> KrisKringleSummary {
        var fields = ["id": String(id)]
        fields["name"] = name
        if let isSecret { fields["secret"] = isSecret ? "1" : "0" }
        let result = try await request("updateKrisKringle", parameters: fields, picture: picture)
        return try KrisKringleSummary(json: result["data"])
    }

    static func addGroup(id: Int, name: String) async throws -> (groupId: Int, roster: KrisKringleRoster) {
        let result = try await request("addKrisKringleGroup", parameters: ["id": String(id), "name": name])
        guard let groupId = result["groupId"].int, groupId > 0 else {
            throw ServiceError("The server didn’t return the new group.")
        }
        return (groupId, try KrisKringleRoster(json: result))
    }

    static func addMember(id: Int, groupId: Int, email: String, firstName: String) async throws -> KrisKringleRoster {
        let fields = ["id": String(id), "groupId": String(groupId), "email": email, "firstName": firstName]
        let result = try await request("addKrisKringleMember", parameters: fields)
        return try KrisKringleRoster(json: result)
    }

    static func updateMember(
        id: Int, memberId: Int?, userId: Int, firstName: String, email: String
    ) async throws -> KrisKringleRoster {
        var fields = [
            "id": String(id), "firstName": firstName, "email": email, "expectedUserId": String(userId)
        ]
        if let memberId {
            fields["memberId"] = String(memberId)
        } else {
            fields["userId"] = String(userId)
        }
        let result = try await request("updateKrisKringleMember", parameters: fields)
        return try KrisKringleRoster(json: result)
    }

    static func addChild(id: Int, parentUserId: Int, name: String) async throws -> KrisKringleRoster {
        let fields = ["id": String(id), "parentUserId": String(parentUserId), "name": name]
        let result = try await request("addKrisKringleChild", parameters: fields)
        return try KrisKringleRoster(json: result)
    }

    static func updateAllGiftRecipients(id: Int, clear: Bool) async throws -> KrisKringleRoster {
        let result = try await request(clear ? "clearGiftRecipients" : "setGiftRecipients", parameters: ["id": String(id)])
        return try KrisKringleRoster(json: result)
    }

    static func giftRecipientOptions(id: Int, giverId: KrisKringleGiftGiverID) async throws -> KrisKringleGiftOptions {
        let giver = giverId.parameter
        let fields = ["id": String(id), giver.name: giver.value]
        return try await KrisKringleGiftOptions(json: request("getGiftRecipientOptions", parameters: fields))
    }

    static func updateGiftAssignment(
        id: Int, giverId: KrisKringleGiftGiverID, recipientId: Int?, expectedRecipientId: Int?
    ) async throws -> KrisKringleRoster {
        let giver = giverId.parameter
        let fields = [
            "id": String(id), giver.name: giver.value, "recipientId": String(recipientId ?? 0),
            "expectedRecipientId": String(expectedRecipientId ?? 0)
        ]
        return try await KrisKringleRoster(json: request("updateGiftAssignment", parameters: fields))
    }

    static func memberPicture(id: Int, userId: Int) async throws -> Data {
        try await authenticatedRequest(
            "getKrisKringleMemberPicture", parameters: ["id": String(id), "userId": String(userId)]
        )
        .validate(statusCode: 200..<300)
        .serializingData().value
    }

    static func picture(id: Int) async throws -> Data {
        try await authenticatedRequest("getKrisKringlePicture", parameters: ["id": String(id)])
            .validate(statusCode: 200..<300)
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
