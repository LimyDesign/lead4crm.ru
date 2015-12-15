var self = document.querySelector('script[data-name="amocrm"]'),
    script_src = self.getAttribute('src');

(function($){
    var $citysearch = $('[id^=inputCitySearch]'),
        $inputRubricSearch = $('#inputRubricSearch'),
        $inputTextSearch = $('#inputTextSearch'),
        $formTextSearch = $('#formTextSearch'),
        $resultTable = $('#resultTable'),
        $gototab = $('.gototab');

    if (localStorage.getItem('2GISRubrics')) {
        var rubrics = JSON.parse(localStorage.getItem('2GISRubrics'));
        var level2 = null,
            level3 = null;
        rubrics.forEach(function (entry) {
            if (!entry.parent) {
                $inputRubricSearch
                    .append('<option data-divider="true"></option>')
                    .append('<option value="' + entry.id + '">'+entry.name+'</option>');
                level2 = entry.id;
            } else if (level2 == entry.parent) {
                $inputRubricSearch
                    .append('<option value="'+entry.id+'" data-content="<span class=\'text-muted\'>&mdash;&nbsp;</span><span class=\'text\'>'+entry.name+'</span>">'+entry.name+'</option>');
                level3 = entry.id;
            } else if (level3 == entry.parent) {
                $inputRubricSearch
                    .append('<option value="'+entry.id+'" data-content="<span class=\'text-muted\'>&mdash;&nbsp;&mdash;&nbsp;</span><span class=\'text\'>'+entry.name+'</span>">'+entry.name+'</option>');
            }
        });
        $inputRubricSearch.selectpicker('render');
    } else {
        var postData = {
            importAPI: getParamByName('apikey'),
            importDomain: getParamByName('subdomain') + '.amocrm.ru'
        };
        $.post('/getRubricList/', postData, function (data) {
            if (data.error == 0) {
                if (data.rubrics) {
                    localStorage.setItem('2GISRubrics', JSON.stringify(data.rubrics));
                    var level2 = null,
                        level3 = null;
                    data.rubrics.forEach(function (entry) {
                        if (!entry.parent) {
                            $inputRubricSearch
                                .append('<option data-divider="true"></option>')
                                .append('<option value="'+entry.id+'">'+entry.name+'</option>');
                            level2 = entry.id;
                        } else if (level2 == entry.parent) {
                            $inputRubricSearch
                                .append('<option value="'+entry.id+'" data-content="<span class=\'text-muted\'>&mdash;&nbsp;</span><span class=\'text\'>'+entry.name+'</span>">'+entry.name+'</option>');
                            level3 = entry.id;
                        } else if (level3 == entry.parent) {
                            $inputRubricSearch
                                .append('<option value="'+entry.id+'" data-content="<span class=\'text-muted\'>&mdash;&nbsp;&mdash;&nbsp;</span><span class=\'text\'>'+entry.name+'</span>">'+entry.name+'</option>');
                        }
                    });
                    $inputRubricSearch.selectpicker('render');
                }
            }
        }, 'json');
    }
    $resultTable.stickyTableHeaders();

    $gototab.click(function() {
        var target = $(this).attr('href');
        $('#main-tabs a:last').tab('show');
        //$(target).tab('show');
    });

    $formTextSearch.submit(function(e){
        e.preventDefault();
        var inputCity = $citysearch.val(),
            inputText = $inputTextSearch.val();
        if (!inputCity) {
            $citysearch.focus();
            return false;
        } else if (!inputText) {
            $inputTextSearch.focus();
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