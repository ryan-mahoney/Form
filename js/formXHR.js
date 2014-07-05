$(document).ready(function () {
    var submittedData = [];
    var submittedForm;
    $('form[data-xhr="true"]').ajaxForm({
        type: 'post',
        dataType: 'json',
        beforeSerialize: function(form, options) { 
            if (typeof CKEDITOR !== "undefined") {
                for(var instanceName in CKEDITOR.instances) {
                    CKEDITOR.instances[instanceName].updateElement();
                }
            }
            formUI.errorClear(form);
            formUI.loading(form);
        },
        beforeSubmit: function(arr, form, options) {
            submittedData = arr;
            submittedForm = form;
        },
        success: function (response, status, xhr, form) {
            formUI.loaded(form);
            if (response['success'] == true) {
                switch (response['after']) {
                    case 'redirect':
                        window.location = response['redirect'];
                        break;

                    case 'notice':
                        formUI.noticeShow(form, response['notice'], response['noticeDetails']);
                        break;

                    case 'function':
                        window[response['function']](form, submittedData, response);
                        break;

                    case 'another':
                        break;
                }
            } else {
                if (typeof(FormError) == "function") {
                    FormError(response['errors']);
                }
                for (marker in response['errors']) {
                    formUI.errorShow(form, marker, response['errors'][marker]);
                }
            }
        },
        error: function (xhr, status, error) {
            var response = xhr['responseJSON'];
            if (typeof(FormError) == "function") {
                FormError(response['errors']);
            }
            for (marker in response['errors']) {
                formUI.errorShow(submittedForm, marker, response['errors'][marker]);
            }
        }
    });
});