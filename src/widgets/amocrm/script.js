define(['jquery'], function($){
    var CustomWidget = function () {
        var self = this;
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
            onSave: function(data) {
                var lang = self.i18n('userLang');
                self.crm_post('https://www.lead4crm.ru/checkAPIKey/', { apikey: data.fields.api_key }, function (res) {
                    console.log(res);
                    if (res.userid === null) {
                        alert(lang.errors.apikey);
                        self.set_status('error');
                    }
                }, 'json', function() {
                    alert(lang.errors.connection);
                    self.set_status('error');
                });
                return true;
            }
        };
        return this;
    };
    return CustomWidget;
});