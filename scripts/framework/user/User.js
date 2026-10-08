var User = {}

User.getUser = function( callback ) {
	$.getJSON( '/json/users/users/getUser', function( user ) {
		callback( user );
	});
}

User.getUserPermissions = function( callback ) {
	$.getJSON( '/json/users/users/getUserPermissions', function( permission ) {
		callback( permission );
	});
}

User.getOrganisation = function( callback ) {
    $.getJSON( '/json/framework/organisation/getUserOrg', function( organisation ) {
        callback( organisation );
    });
}