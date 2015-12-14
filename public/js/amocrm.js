var self = document.querySelector('script[data-name="amocrm"]'),
    script_src = self.getAttribute('src'),
    usercity = getParamByName('uc');

(function($){
    var $typeahead = $('#inputCitySearch');
    $.get('/getSupportCities/', function(data){
        $typeahead.typeahead({ source: data, autoselect: true });
        $.each(data, function(key, value) {
            if (value == usercity) {
                $typeahead.val(usercity);
                return false;
            }
        });
    }, 'json');

    $('#formTextSearch').submit(function(e){
        e.preventDefault();
        alert('Yo!');
    });
})(jQuery);

function getParamByName(name) {
    name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
    var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
        results = regex.exec(script_src);
    return results === null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
}