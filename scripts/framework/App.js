var App = {
    GIFT: 1,
    version: '1.0.0'
}

App.loadStyle = function( ref ) {
    $( 'head' ).append( '<link href="' + ref + '?v=' + App.version + '" rel="stylesheet" />' );
}