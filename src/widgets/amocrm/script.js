define(['jquery'], function($){
    var CustomWidget = function () {
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
                crm_post('https://www.lead4crm.ru/')
                alert(data.fields.api_key);
                return true;
            }
        };
        return this;
    };
    return CustomWidget;
});