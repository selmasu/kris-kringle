var DeleteAccount = {}

DeleteAccount.init = function() {
    DeleteAccount.listeners()
}

DeleteAccount.listeners = function(){
    $('#go').click(DeleteAccount.deleteAccount)
}

DeleteAccount.deleteAccount = function(){
    if($('#name').val() !== ''
        && $('#email').val() !== ''){
        $.post('/pajax/userForm/requestDeleteAccount', {name: $('#name').val(), email: $('#email').val()}, function(){
            Utils.alert().bootstrap().show("Request Account Deletion", "Thank you")
            $('#name, #email').val('')
        })
    } else {
        Utils.alert().bootstrap().show("Request Account Deletion", "Please enter your name and email address")
    }
}

$(DeleteAccount.init)