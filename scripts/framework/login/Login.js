$( document ).ready( function() {
    var validator = $( "#login" ).kendoValidator().data( "kendoValidator" );
    var resetRequestValidator = $( "#reset-password-request" ).kendoValidator().data( "kendoValidator" );
    var resetValidator = $( "#reset-password" ).kendoValidator().data( "kendoValidator" );
    var status = $( ".status" );
    var mfa = $( "#mfa" ).val() === '1';
    var oneTimeCodeSignIn = false;

    $( "#sign-in" ).click( login );
    $( "#one-time-code" ).click( oneTimeCode );
    $( "#login #email, #login #password, #login #mfaCode" ).keyup( checkLoginEnter );
    $( "#resendCode" ).click( sendAccessCode )

    $( "#reset-password-request button" ).click( requestResetPassword );
    $( "#reset-password-request #recoverEmail" ).keyup( checkRequestResetEnter );

    $( "#reset-password button" ).click( resetPassword );
    $( "#reset-password #password" ).keyup( checkResetEnter );

    function checkLoginEnter( e ) {
        if( e.keyCode == 13 ) {
            login();
        }
    }

    function checkRequestResetEnter( e ) {
        if( e.keyCode == 13 ) {
            requestResetPassword();
        }
    }

    function checkResetEnter( e ) {
        if( e.keyCode == 13 ) {
            resetPassword();
        }
    }

    function login() {
        if( validator.validate() ) {
            if($('#mfaCode').length > 0){
                $.post('/json/framework/authentication/mfa', {
                    mfaCode: $("#mfaCode").val()
                }, function (result) {
                    if (result.valid) {
                        onLogin()
                    } else if (result.error) {
                        Utils.alert().show( 'Login', result.error );
                    }
                }, 'json');
            } else {
                let signInMethod = oneTimeCodeSignIn ? "loginOneTimeCode" : "login";
                let passwordError = oneTimeCodeSignIn ? "Incorrect one time code" : "Incorrect password";
                $.post( '/json/framework/authentication/' + signInMethod, {
                    email : $( "#email" ).val(),
                    password : $( "#password" ).val(),
                    oneTimeCode : $( "#oneTimeCode" ).val(),
                    rememberMe : $( '#rememberMe:checked' ).val()
                }, function( result ) {
                    if( result.token ) {
                        if(mfa && result.user.mfa){
                            sendAccessCode()
                        } else {
                            onLogin()
                        }
                    } else if( result.error ) {
                        if( result.error == 'passwordError' ) {
                            Utils.alert().show( 'Login', passwordError );
                        } else if( result.error == 'unknownUserError' ) {
                            Utils.alert().show( 'Login', 'Email address not recognised.' );
                        }
                    }
                }, 'json');
            }
        } else {
            if($('#email').length > 0) {
                Utils.alert().show('Login', 'Please enter your email address and password');
            }
        }
    }

    function oneTimeCode() {
        $('#password').prop('required', false)
        if( validator.validate() ) {
            $('#one-time-code').prop('disabled', true)
            $.post('/json/user/user/sendOneTimeCode', {email: $( "#email" ).val()}, function (result) {
                if( result.sent ) {
                    Utils.alert().show( 'Send One Time Code', 'Check your email inbox for your one time code, then return and enter it here' );
                    $('#password-row, #remember-me-row, #send-one-time-code-row').hide()
                    $('#one-time-code-row').show()
                    $('#oneTimeCode').prop('required', true)
                    $('#rememberMe').prop('checked', false)
                    oneTimeCodeSignIn = true
                } else if( result.error ) {
                    $('#one-time-code').prop('disabled', false)
                    Utils.alert().show('Send One Time Code', result.error);
                }
            }, 'json');
        } else {
            if($('#email').length > 0) {
                Utils.alert().show('Login', 'Please enter your email address');
            }
        }
    }

    function sendAccessCode() {
        $.post('/json/user/user/sendMFACode', {delivery: 'email'}, function (result) {
            if (result.sent) {
                window.location.reload()
            } else {
                Utils.alert().show('Send Access Code', result.error)
            }
        }, 'json')
    }

    function onLogin() {
        $.post('/json/user/user/getUser', function (user) {
            if (user.admin || user.guest) {
                document.location = $('#r').val() !== '' ? $('#r').val() : '/admin';
            } else if (user.consultant) {
                $.post('/json/customers/customers/getCustomerByUser', function (customer) {
                    if (customer.countryId == $('#countryId').val()) {
                        document.location = $('#r').val() !== '' ? $('#r').val() : '/my-lereve';
                    } else {
                        $.get('/json/framework/authentication/logout');
                        let oppositeCountryId = $('#countryId').val() == 'NZ' ? 'AUS' : 'NZ';
                        Utils.alert().okCallBack(function () {
                            if ($('#countryId').val() == 'NZ') {
                                document.location = 'https://hub.lereve.com.au/login';
                            } else {
                                document.location = 'https://www.lereve.co.nz/login';
                            }
                        }).show('Consultant Login', 'Please use the Consultant Login link in the footer of the ' + oppositeCountryId + ' website to login as a consultant.<br/>Click OK to be redirected to the ' + oppositeCountryId + ' website now.');
                    }
                }, 'json')
            }
        }, 'json')
    }

    function requestResetPassword() {
        if( resetRequestValidator.validate() ) {
            $.post( '/json/framework/authentication/checkEmail', { email : $( "#email" ).val() }, function( result ) {
                if( result ) {
                    $( '.account-content' ).load( '/ajax/login/reset-password-confirm/' + $( "#email" ).val() );
                } else {
                    Utils.alert().show( 'Reset Password', 'Email address not recognised.' );
                }
            }, 'json' );
        } else {
            Utils.alert().show( 'Reset Password', 'Please enter your email address.' );
        }
    }

    function resetPassword() {
        if( resetValidator.validate() ) {
            $.post( '/json/framework/user/resetPassword', { password : $( "#password" ).val(), token: $( "#resetToken" ).val() }, function( result ) {
                if( result ) {
                    $( '.account-content' ).load( '/ajax/login/password-reset-successful' );
                } else {
                    status.text( 'Reset password link has expired' ).addClass( 'invalid' );
                }
            }, 'json' );
        } else {
            Utils.alert().show( 'Reset Password', 'Please enter a password.' );
        }
    }
});