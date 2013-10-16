var formUI = {
	errorShow: function (formNode, marker, errors) {	
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