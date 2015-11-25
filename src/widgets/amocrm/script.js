define(['jquery', 'lib/components/base/modal'], function($, Modal) {
    var CustomWidget = function () {
        var self = this,
            LEAD4CRM = {};

        this.add_call_notify = function(mess) {
            var w_name = self.i18n('widget').name,
                date_now = Math.ceil(Date.now()/1000),
                lang = self.i18n('settins'),
                text,
                header;
            if (mess.id > 0) {
                text = mess.text;
            }
            header = w_name + ': ' + mess.title;
            var n_data = {
                    header: header,
                    text: '<p>'+text+'</p>',
                    date: date_now
                },
                callbacks = {
                    done: function() {},
                    fail: function() {},
                    always: function() {}
                };
            AMOCRM.notifications.add_error(n_data, callbacks);
        };

        this.check_api_key = function(apikey, callback) {
            var lang = self.i18n('userLang'),
                notify_data = {};
            self.crm_post('https://www.lead4crm.ru/checkAPIKey/', { apikey: apikey }, function(data) {
                if (data.userid === false) {
                    notify_data.id = 2;
                    notify_data.title = lang.errors.apikey.title;
                    notify_data.text = lang.errors.apikey.text;
                    self.add_call_notify(notify_data);
                    self.set_status('error');
                } else {
                    self.set_status('installed');
                    if (callback && typeof(callback) === 'function') {
                        callback();
                    }
                }
            }, 'json', function() {
                notify_data.id = 1;
                notify_data.title = lang.errors.connection.title;
                notify_data.text = lang.errors.connection.text;
                self.add_call_notify(notify_data);
                self.set_status('error');
            });
        };

        this.get_lead4crm_data = function(apikey, callback) {
            var lang = self.i18n('userLang'),
                notify_data = {};
            LEAD4CRM.tariff = lang.default_tariff;
            LEAD4CRM.qty = 0;
            self.crm_post('http://www.lead4crm.ru/getAmoUserData/', { apikey: apikey }, function(data) {
                LEAD4CRM.tariff = data.tariff;
                LEAD4CRM.qty = data.qty;
                if (callback && typeof(callback) === 'function') {
                    callback(LEAD4CRM);
                }
            }, 'json', function() {
                notify_data.id = 1;
                notify_data.title = lang.errors.connection.title;
                notify_data.text = lang.errors.connection.text;
                self.add_call_notify(notify_data);
            });
        };

        this.callbacks = {
            init: function() {
                var settings = self.get_settings();
                if (settings.widget_active == 'Y') {
                    self.check_api_key(settings.api_key);
                }
                return true;
            },
            render: function() {
                if (self.system().area != 'settings') {
                    var lang = self.i18n('userLang');
                    self.get_lead4crm_data(self.params.api_key, function(ud) {
                        self.render_template({
                            caption: {
                                class_name: 'js-lead4crm-caption',
                                html: '<image class="lead4crm_logo" src="' + self.params.path + '/images/logo.png" />'
                            },
                            body: '<link type="text/css" rel="stylesheet" href="' + self.params.path + '/style.css">',
                            render: '<div class="lead4crm-form"><p class="helper">' + lang.help + ' <span id="lead4crm-qty">' + ud.qty + '</span></p><div class="lead4crm-form-button lead4crm_sub">' + lang.modal_button + '</div></div>'
                        });
                    });
                }
                return true;
            },
            bind_actions: function() {
                var $button = $('.lead4crm-form-button.lead4crm_sub'),
                    lang = self.i18n('userLang'),
                    data = '<h2 class="content__top__preset__caption">' + lang.modal_title + '</h2>';
                $button.on(AMOCRM.click_event + self.ns, function() {
                    new Modal({
                        class_name: 'modal-lead4crm',
                        init: function ($modal_body) {
                            var $this = $(this);
                            $modal_body
                                .trigger('modal:loaded')
                                .css('width', '90%')
                                .html(data)
                                .trigger('modal:centrify')
                                .append('<span class="modal-body__close"><span class="icon icon-modal-close"></span></span>');
                        },
                        destroy: function() {}
                    });
                });
                return true;
            },
            onSave: function(data) {
                if (data.active == 'Y') {
                    self.check_api_key(data.fields.api_key);
                } else {
                    self.set_status('install');
                }
                return true;
            }
        };
        return this;
    };
    return CustomWidget;
});