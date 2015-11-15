define(['jquery', 'lib/components/base/modal'], function($, Modal) {
	var Lead4CRM = function () {
		var self = this,
			system = self.system(),
			settings = self.get_settings(),
			lang = self.i18n('userLang'),
			qty = 0,
			tariff = null,
			errors = AMOCRM.notifications,
			w_code = self.get_settings().widget_code,
			this.callbacks = {
				settings: function() {

				},
				init: function() {
					self.crm_post(
						'https://www.lead4crm.ru/getB24UserData/',
						{
							apikey: settings.apikey
						},
						function (data) {
							qty = data.qty;
							tariff = data.tariff;
							if (qty == 0) {
								var date_now = Math.ceil(Date.now()/1000),
									n_data = {
										header: w_code,
										text: '<p>'+lang.errors.empty_qty+'</p>',
										date: date_now
									};
								errors.add_error(n_data);
								return false;
							} else {
								return true;
							}
						},
						'json',
						function () {
							var date_now = Math.ceil(Date.now()/1000),
								n_data = {
									header: w_code,
									text: '<p>'+lang.errors.connection+'</p>',
									date: date_now
								};
							errors.add_error(n_data);
							return false;
						}
					);
				},
				bind_actions: function() {
					return true;
				},
				render: function() {
					var html_data = '<div class="nw_form">'+
							'<div id="w_logo">'+
							'<img src="/widgets/'+w_code+'/images/logo.png" id="firstwidget_image"></img>'+
							'</div>'+
							'<div id="js-sub-lists-container">'+
							'</div>'+
							'<div id="js-sub-subs-container">'+
							'<div>'+
							'<div class="nw-form-button">BUTTON</div></div>'+
							'<div class="already-subs"></div>';
					self.render_template({
						caption: {
							class_name: 'lead4crm',
							html: ''
						},
						body: html_data,
						render: ''
					});
					return true;
				},
				destroy: function() {

				},
				onSave: function() {

				}
			};
		return this;
	};
	return Lead4CRM;
});