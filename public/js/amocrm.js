var self = document.querySelector('script[data-name="amocrm"]'),
    script_src = self.getAttribute('src'),
    usercity = getParamByName('uc');

(function($){
    var $citysearch = $('#inputCitySearch');
    $citysearch.typeahead({
        source: $.get('/getSupportCities/'),
        autocomplete: true
    });

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