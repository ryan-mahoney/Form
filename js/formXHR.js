$(document).ready(function () {
    var submittedData = [];
    var submittedForm;
    $('form[data-xhr="true"]').ajaxForm({
        type: 'post',
        dataType: 'json',
        beforeSerialize: function(form, options) {
            formUI.errorClear(form);
            formUI.loading(form);
        },
        beforeSubmit: function(arr, form, options) {
            submittedData = arr;
            submittedForm = form;
        },
        success: function (response, status, xhr, form) {
            if (response['api_token'] != undefined) {
                createCookie('api_token', response['api_token'], 90);
            }
            formUI.loaded(form);
            if (response['success'] == true) {
                switch (response['after']) {
                    case 'redirect':
                        window.location = response['redirect'];
                        window.location.reload();
                        break;

                    case 'notice':
                        formUI.noticeShow(form, response['notice'], response['noticeDetails']);
                        break;

                    case 'function':
                        window[response['function']](form, submittedData, response);
                        break;

                    case 'refresh':
                        window.location.reload();
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

var createCookie = function(name, value, days) {
    var expires;
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toGMTString();
    } else {
        expires = "";
    }
    document.cookie = escape(name) + "=" + escape(value) + expires + "; path=/";
};