var EmailForm = {
    viewModel: null,
    editor: null,

    TYPE_ONE_TIME_CODE: 1,
};

EmailForm.addStylesheet = function() {
    $( 'head' ).append( '<link href="/templates/app/css/emailForm.css" rel="stylesheet" />' );
}

EmailForm.getViewModel = function()
{
    //Define the viewModel
    var viewModel = kendo.observable({
        emailId: _emailId,
        name: '',
        subject: '',
        fromAddress: '',
        toAddress: '',
        ccAddress: '',
        bccAddress: '',
        active: 0,
        dynamicTo: 0,
        load: function() {
            var self = this;

            if( _emailId )
            {
                $.getJSON( '/json/emails/emails/getEmail', { emailId : _emailId }, function( email )
                {
                    for( var key in email )
                    {
                        self.set( key, email[key] );
                    }

                    $( '#active' ).attr( 'checked', self.get( 'active' ) == 1 );

                    EmailForm.addKendoElements();
                });
            }
        },
        isDynamic: function() {
            return this.get( 'dynamicTo' ) == 1;
        }
    });

    return viewModel;
}

EmailForm.loadViewModel = function()
{
    EmailForm.viewModel = EmailForm.getViewModel();
    kendo.bind( $( '#email' ), EmailForm.viewModel );
    EmailForm.viewModel.load();
}

EmailForm.addListeners = function()
{
    $( "#emailsLink" ).click( EmailForm.showEmailList );
    $( "#cancelButton" ).click( EmailForm.showEmailList );
    $( "#insertPlaceholder" ).click( EmailForm.insertPlaceholder );

    $( "#saveButton" ).click( function()
    {
        EmailForm.validateForm( false );
    });

    $( "#doneButton" ).click( function()
    {
        EmailForm.validateForm( true );
    });
}

EmailForm.showEmailList = function()
{
    $( "#mainContentDiv" ).load( "/ajax/emailList" );
}

EmailForm.validator = function()
{
    return $( "#email" ).kendoValidator().data( "kendoValidator" );
}

EmailForm.status = function()
{
    return $( "span.status" );
}

EmailForm.disableSaveButtons = function()
{
    $( "#saveButton" ).prop( 'disabled', true );
    $( "#doneButton" ).prop( 'disabled', true );
}

EmailForm.enableSaveButtons = function()
{
    $( "#saveButton" ).prop( 'disabled', false );
    $( "#doneButton" ).prop( 'disabled', false );
}

EmailForm.validateForm = function( returnToList )
{
    if( EmailForm.validator().validate() )
    {
        EmailForm.save( returnToList );
    }
    else
    {
        EmailForm.status().text( 'Please complete all required fields.' ).addClass( 'invalid' );
        EmailForm.enableSaveButtons();
    }
}

EmailForm.save = function( returnToList )
{
    EmailForm.disableSaveButtons();

    var data = $( "#emailForm" ).serialize();
    if( !EmailForm.viewModel.isDynamic() )
    {
        data += '&toAddress=' + encodeURI( $( '#toAddress' ).data('kendoMultiSelect' ).value().join( ';' ) );
    }

	data += '&ccAddress=' + encodeURI( $( '#ccAddress' ).data('kendoMultiSelect' ).value().join( ';' ) );
	data += '&bccAddress=' + encodeURI( $( '#bccAddress' ).data('kendoMultiSelect' ).value().join( ';' ) );

    $.post( "/ajax/settings/emailSave", data, function( emailId )
    {
        if( parseInt( emailId ) > 0 )
        {
            EmailForm.status().text( 'Email Saved' ).addClass( 'valid' ).fadeOut( 2000 );
            if( returnToList )
            {
                EmailForm.showEmailList();
            }

            EmailForm.enableSaveButtons();
        }
        else
        {
            EmailForm.status().text( "Email could not be saved" ).addClass( 'invalid' );
            EmailForm.enableSaveButtons();
        }
    });
}

EmailForm.addKendoElements = function()
{
	if( !EmailForm.editor )
    {
        EmailForm.editor = $( 'textarea#htmlBody' ).ckeditor({
            customConfig: '/scripts/framework/libs/ckeditor/email.js'
        }).editor;
    }

    EmailForm.toAddresses();
	EmailForm.ccAddresses();
	EmailForm.bccAddresses();
}

EmailForm.toAddresses = function()
{
    if( !EmailForm.viewModel.isDynamic() )
    {
        $( '#toAddress' ).html( '' );
        $.getJSON( '/json/framework/user/getAdminUsers', function( users )
        {
            for( var i = 0; i < users['data'].length; i++ )
            {
	            if( users['data'][i]['email'] )
	            {
	                $( '#toAddress' ).append(
	                    $( document.createElement( 'option' ) )
	                        .html( users['data'][i]['firstName'] + ' ' + users['data'][i]['lastName'] )
	                        .val( users['data'][i]['email'] )
	                );
	            }
            }

            $( '#toAddress' ).kendoMultiSelect().data( 'kendoMultiSelect' ).value( EmailForm.viewModel.get( 'toAddress' ).split( ';' ) );
        });
    }
}

EmailForm.ccAddresses = function()
{
	$( '#ccAddress' ).html( '' );
	$.getJSON( '/json/framework/user/getAdminUsers', function( users )
	{
		for( var i = 0; i < users['data'].length; i++ )
		{
			if( users['data'][i]['email'] )
			{
				$( '#ccAddress' ).append(
					$( document.createElement( 'option' ) )
						.html( users['data'][i]['firstName'] + ' ' + users['data'][i]['lastName'] )
						.val( users['data'][i]['email'] )
				);
			}
		}

		$( '#ccAddress' ).kendoMultiSelect().data( 'kendoMultiSelect' ).value( EmailForm.viewModel.get( 'ccAddress' ).split( ';' ) );
	});
}

EmailForm.bccAddresses = function()
{
	$( '#bccAddress' ).html( '' );
	$.getJSON( '/json/framework/user/getAdminUsers', function( users )
	{
		for( var i = 0; i < users['data'].length; i++ )
		{
			if( users['data'][i]['email'] )
			{
				$( '#bccAddress' ).append(
					$( document.createElement( 'option' ) )
						.html( users['data'][i]['firstName'] + ' ' + users['data'][i]['lastName'] )
						.val( users['data'][i]['email'] )
				);
			}
		}

		$( '#bccAddress' ).kendoMultiSelect().data( 'kendoMultiSelect' ).value( EmailForm.viewModel.get( 'bccAddress' ).split( ';' ) );
	});
}

$( document ).ready( function()
{
    EmailForm.addStylesheet();
    EmailForm.loadViewModel();
    EmailForm.addListeners();
});