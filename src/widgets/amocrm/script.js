define(['jquery', 'lib/components/base/modal'], function($, Modal) {
	var CustomWidget = function () {
		var self = this,
			this.callbacks = {
				init: function() {
					return true;
				},
				bind_actions: function() {
					return true;
				},
				render: function() {
					return true;
				},
				settings: function() {
					alert('Yo!');
				}
			};
		return this;
	};
	return CustomWidget;
});