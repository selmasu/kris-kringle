CKEDITOR.editorConfig = function( config ) {
    config.uiColor = '#ffffff';
    config.extraPlugins = 'tableresize,autogrow';
    config.autoGrow_minHeight = 150;
    config.width = 550;
    config.autoGrow_onStartup = true;
    config.removePlugins = 'save,print';
    config.filebrowserUploadUrl = '/file/ckupload';
    config.toolbarGroups = [
        { name: 'clipboard',   groups: [ 'clipboard', 'undo' ] },
        { name: 'editing',     groups: [ 'find', 'selection', 'spellchecker' ] },
        { name: 'links' },
        { name: 'insert' },
        { name: 'tools' },
        { name: 'document',    groups: [ 'mode', 'document', 'doctools' ] },
        { name: 'others' },
        { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
        { name: 'paragraph',   groups: [ 'list', 'indent', 'blocks', 'align' ] },
        { name: 'styles' },
        { name: 'colors' },
        { name: 'about' }
    ];
};