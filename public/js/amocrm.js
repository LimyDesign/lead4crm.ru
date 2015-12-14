(function($){
    var $typeahead = $('#inputCitySearch');
    $.get('/getSupportCities/', function(data){

        $typeahead.typeahead({ source: data, autoselect: true });
    }, 'json');

    $('#formTextSearch').submit(function(e){
        e.preventDefault();
        alert('Yo!');
    });
})(jQuery);