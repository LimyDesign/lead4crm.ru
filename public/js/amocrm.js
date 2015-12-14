var self = document.querySelector('script[data-name="amocrm"]'),
    script_src = self.getAttribute('src'),
    usercity = getParamByName('uc');

(function($){
    var $citysearch = $('#inputCitySearch');
    $.get('/getSupportCities/', function(data) {
        $citysearch.typeahead({
            source: data,
            autocomplete: true
        });
        data.forEach(function (entiry) {
            if (entiry.name == usercity) {
                $citysearch.val(usercity);
            }
        });
    });
    $citysearch.change(function() {
        var current = $citysearch.typeahead("getActive");
        if (current) {
            if (current.name != $citysearch.val()) {
                $citysearch.val('');
            }
        } else {
            $citysearch.val('');
        }
    });

    $('#formTextSearch').submit(function(e){
        e.preventDefault();
        var inputCity = $citysearch.val(),
            inputText = $('#inputTextSearch').val();
        if (!inputCity || !inputText) {
            alert('Необходимо заполнить все поля.');
            return false;
        }
    });
})(jQuery);

function getParamByName(name) {
    name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
    var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
        results = regex.exec(script_src);
    return results === null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
}