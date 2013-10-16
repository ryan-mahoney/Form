$(document).ready(function () {
    var submittedData = [];
    $('form[data-xhr="true"]').ajaxForm({
        type: 'post',
        dataType: 'json',
        beforeSerialize: function(form, options) { 
            formUI.errorClear(form);
            formUI.loading(form);
        },
        beforeSubmit: function(arr, form, options) {
            submittedData = arr;
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
                        window[response['function']](form, submittedData);
                        break;

                    case 'another':
                        break;
                }
            } else {
                for (marker in response['errors']) {
                    formUI.errorShow(form, marker, response['errors'][marker]);
                }
            }
        }
    });
});