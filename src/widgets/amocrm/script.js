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
                    alert('Хуй!');
                    console.log(res);
                    return true;
                }, 'json', function() {
                    alert(lang.errors.connection);
                    self.set_status('error');
                    return false;
                });
            }
        };
        return this;
    };
    return CustomWidget;
});