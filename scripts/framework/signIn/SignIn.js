var SignIn = {}

SignIn.init = function(){
	SignIn.listeners()
	SignIn.appleSignIn()
}

SignIn.listeners = function(){
	$('#sign-in').click(SignIn.signIn)
}

SignIn.googleClient = function(){
	return google.accounts.oauth2.initCodeClient({
		client_id: '382392073189-s2qt83rvrmcl8v5mom9hg9uve1togris.apps.googleusercontent.com',
		scope: 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email',
		ux_mode: 'popup',
		callback: (response) => {
			$.getJSON('/json/framework/authentication/loginGoogle', {code: response.code, rememberMe: true}, function(result){
				if(result.token){
					SignIn.associateTrips()
				}
			})
		},
	});
}

SignIn.appleSignIn = function(){

}

SignIn.associateTrips = function(){
	$.getJSON('/json/trip/trip/associateTrips',function(){
		document.location.reload()
	})
}

SignIn.signIn = function(){
	SignIn.googleClient().requestCode()
}

$(SignIn.init)