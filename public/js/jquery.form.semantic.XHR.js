(function($) {
    $.fn.xhrForm = function() {

        //interactions after form is submitted
        var formUI = {
            errorShow: function (formNode, marker, errors) {
                formNode.removeClass('loading');
                formNode.addClass('warning');
                var listNode = formNode.find('.ui.warning.message ul');
                var fieldNode;
                for (field in errors) {
                    listNode.append('<li>' + errors[field] + '</li>');
                    fieldNode = formNode.find('*[name="' + marker + '[' + field + ']"]');
                    fieldNode.parents('.field').addClass('error');
                    fieldNode.after('<div class="ui red pointing above ui label" data-fielderror="true">' + errors[field] + '</div>');
                }
            },
            errorClear: function (formNode) {
                formNode.removeClass('warning');
                formNode.find('.field').removeClass('error');
                formNode.find('.ui.warning.message ul li').remove();
                formNode.find('div[data-fielderror="true"]').remove();
                formNode.find('div.info.message').remove();
            },
            loading: function (formNode) {
                formNode.addClass('loading');
            },
            loaded: function (formNode) {
                formNode.removeClass('loading');
            },
            noticeShow: function (formNode, notice, noticeDetails) {
                formNode.prepend('<div class="ui info message" style="display: block"><div class="header">' + notice + '</div></div>');
                if (noticeDetails != '') {
                    formNode.find('div.info.message').append('<p>' + noticeDetails + '</p>');
                }
            }
        };

        //create an api_key cookie
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

        var submittedData = [];
        var submittedForm;

        this.filter("form").each(function() {
            $(this).ajaxForm({
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
        return this;
    };
}(jQuery));

$(document).ready(function() {
    $('form[data-xhr="true"]').xhrForm();
});