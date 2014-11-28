if ($('form[data-xhr="true"]').length) {
    console.log("Loading Opine Form");
    require.ensure([], function(require) {
        var $ = require('jquery');
        require('semantic');
        require('jquery-form');
        require('jquery.form.semantic.XHR.js');
    });
} else {
    console.log("Skipping Loading Opine Form");
}
