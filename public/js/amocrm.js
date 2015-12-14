(function($){
    var $typeahead = $('#inputCitySearch');
    $typeahead.typeahead({
        source: function(handleData){
            $.get('/getSupportCities/', function(data){
                handleData(data);
            });
            return handleData;
        },
        autoselect: true
    });
    $typeahead.change(function() {
        var current = $typeahead.typeahead("getActive");
        if (current) {
            if (current.name == $typeahead.val()) {
                alert('Yo!');
            } else {
                alert('Fuuu');
            }
        } else {
            alert('123123');
        }
    });

    $('#formTextSearch').submit(function(e){
        e.preventDefault();
        alert('Yo!');
    });
})(jQuery);