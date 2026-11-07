//
//  Utils.swift
//  Kris Kringle
//
//  Created by Lucas Sparke on 5/11/2026.
//

import Foundation
import UIKit
import SwiftUI
import LocalAuthentication

enum BiometricType {
    case none
    case touch
    case face
}

open class Utils {
    
    class func requestURL( _ app:String, service: String, function: String ) -> String {
        return Constants.appURL + "/json/" + app + "/" + service + "/" + function
    }
    
    class func requestActionURL( _ request:String, page: String, action: String ) -> String {
        return Constants.appURL + "/" + request + "/" + page + "/" + action + "?selmasuToken=" + Globals.selmasuToken
    }
    
    class func requestParams(_ parameters: [String: Any]) -> [String:AnyObject] {
        var params = parameters
        params["selmasuToken"] = Globals.selmasuToken
        
        return params.mapValues { $0 as AnyObject }
    }
    
    class func dateToTimeStamp( _ date:Date ) -> String {
        let dateFormatter = DateFormatter()
        dateFormatter.dateFormat = "yyyyMMddHHmmss"
        return dateFormatter.string( from: date ) as String
    }
    
    class func dateToMySqlDateTime( _ date:Date ) -> String {
        let dateFormatter = DateFormatter()
        dateFormatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
        return dateFormatter.string( from: date )
    }
    
    class func dateToMySqlDate( _ date:Date ) -> String {
        let dateFormatter = DateFormatter()
        dateFormatter.dateFormat = "yyyy-MM-dd"
        return dateFormatter.string( from: date )
    }
    
    class func dateToYear( _ date:Date ) -> String {
        let dateFormatter = DateFormatter()
        dateFormatter.dateFormat = "yyyy"
        return dateFormatter.string( from: date )
    }
    
    class func mySqlDateTimeToDate( _ dateTimeString:String ) -> Date {
        let dateFormatter = DateFormatter()
        dateFormatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
        if let date = dateFormatter.date( from: dateTimeString ) {
            return date
        }
        
        return Date()
    }
    
    class func dateToMySqlTime( _ date:Date ) -> String {
        let dateFormatter = DateFormatter()
        dateFormatter.dateFormat = "HH:mm:ss"
        return dateFormatter.string( from: date )
    }
    
    class func mySqlDateToDate( _ dateString:String ) -> Date {
        if dateString == "0000-00-00" {
            return Date()
        }
        
        let dateFormatter = DateFormatter()
        dateFormatter.dateFormat = "yyyy-MM-dd"
        return dateFormatter.date( from: dateString )!
    }
    
    class func mySqlTimeToDate( _ dateString:String ) -> Date {
        let dateFormatter = DateFormatter()
        dateFormatter.dateFormat = "HH:mm:ss"
        return dateFormatter.date( from: dateString )!
    }
    
    class func mysqlDateTimeIsSet( _ dateString:String? ) -> Bool {
        if let date = dateString {
            if date != "0000-00-00 00:00:00" {
                return true
            }
        }
        
        return false
    }
    
    class func mysqlDateIsSet( _ dateString:String? ) -> Bool {
        if let date = dateString {
            if !date.isEmpty && date != "0000-00-00" {
                return true
            }
        }
        
        return false
    }
    
    class func formatDateShort( _ date:Date ) -> String {
        let dateFormatter = DateFormatter()
        dateFormatter.dateFormat = "MMM dd"
        return dateFormatter.string( from: date )
    }
    
    class func formatDate( _ date:Date ) -> String {
        let dateFormatter = DateFormatter()
        dateFormatter.dateFormat = "MMM dd yyyy"
        return dateFormatter.string( from: date )
    }
    
    class func formatTime( _ date:Date ) -> String {
        let dateFormatter = DateFormatter()
        dateFormatter.dateFormat = "h:mm a"
        return dateFormatter.string( from: date )
    }
    
    class var now:Date {
        return Date()
    }
    
    class var todayMySQLFormat:String {
        return dateToMySqlDate(Date())
    }
    
    class var appDocumentsDir:NSString {
        let nsDocumentDirectory = FileManager.SearchPathDirectory.documentDirectory
        let nsUserDomainMask = FileManager.SearchPathDomainMask.userDomainMask
        let paths = NSSearchPathForDirectoriesInDomains(nsDocumentDirectory, nsUserDomainMask, true)
        if paths.count > 0 {
            return paths[0] as NSString
        }
        
        return ""
    }
    
    class func alert( _ title:String, message:String, sender:UIViewController ) {
        let alertController = UIAlertController(title: title, message: message, preferredStyle: UIAlertController.Style.alert)
        
        let OKAction = UIAlertAction(title: "OK", style: UIAlertAction.Style.default) { (action) in }
        alertController.addAction(OKAction)
        
        sender.present(alertController, animated: true) {}
    }
    
    class func resizeImage(image: UIImage, newSize: CGSize) -> UIImage? {
        let size = image.size
        
        let widthRatio  = newSize.width / size.width
        let heightRatio = newSize.height / size.height
        
        var newSize: CGSize
        if(widthRatio < heightRatio) {
            newSize = CGSize(width: size.width * heightRatio, height: size.height * heightRatio)
        } else {
            newSize = CGSize(width: size.width * widthRatio, height: size.height * widthRatio)
        }
        
        let rect = CGRect(origin: .zero, size: newSize)
        
        UIGraphicsBeginImageContextWithOptions(newSize, false, 1.0)
        image.draw(in: rect)
        let newImage = UIGraphicsGetImageFromCurrentImageContext()
        UIGraphicsEndImageContext()
        
        return newImage
    }
    
    static func biometricType() -> BiometricType {
        let authContext = LAContext()
        if #available(iOS 11, *) {
            let _ = authContext.canEvaluatePolicy(.deviceOwnerAuthenticationWithBiometrics, error: nil)
            switch(authContext.biometryType) {
            case .touchID:
                return .touch
            case .faceID:
                return .face
            default:
                return .none
            }
        } else {
            return authContext.canEvaluatePolicy(.deviceOwnerAuthenticationWithBiometrics, error: nil) ? .touch : .none
        }
    }
}
