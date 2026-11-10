CKEDITOR.editorConfig = function( config ) {
    config.uiColor = '#ffffff';
    config.extraPlugins = 'tableresize';
    config.height = 600;
    config.width = 800;
    config.autoGrow_onStartup = true;
    config.removePlugins = 'save,print';
    config.filebrowserUploadUrl = '/file/ckupload';
    config.allowedContent = true;
    config.fullPage = true;
    config.toolbarGroups = [
        { name: 'links' },
        { name: 'insert' },
        { name: 'document',    groups: [ 'mode', 'document' ] },
        { name: 'others' },
        { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
        { name: 'editing',     groups: [ 'find', 'spellchecker' ] },
        { name: 'paragraph',   groups: [ 'list', 'indent', 'blocks', 'align' ] },
        { name: 'styles' },
        { name: 'colors' },
        { name: 'about' }
    ];
};