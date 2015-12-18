var self = document.querySelector('script[data-name="amocrm"]'),
    script_src = self.getAttribute('src');

(function($){
    var $inputRubricSearch = $('#inputRubricSearch'),
        $inputTextSearch = $('#inputTextSearch'),
        $formTextSearch = $('#formTextSearch'),
        $formRubricSearch = $('#formRubricSearch'),
        $resultTable = $('#resultTable'),
        $gototab = $('.gototab'),
        $tabs = $('#main-tabs');

    toastr.options = {
        "closeButton": false,
        "debug": false,
        "newestOnTop": false,
        "progressBar": true,
        "positionClass": "toast-top-center",
        "preventDuplicates": false,
        "onclick": null,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    };

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
        $tabs.find('a[href="'+target+'"]').tab('show');
    });

    $formTextSearch.submit(function(e) {
        e.preventDefault();
        var selectCity = $(this).find('[name="selectSearchCity"]'),
            inputText = $inputTextSearch;
        if (!selectCity.val()) {
            toastr['error']('Выберите город для поиска');
            return false;
        } else if (!inputText.val()) {
            toastr['error']('Введите текст поиска');
            inputText.focus();
            return false;
        }
    });

    $formRubricSearch.submit(function(e) {
        e.preventDefault();
        var selectCity = $(this).find('[name="selectSearchCity"]');
        if (!selectCity.val()) {
            toastr['error']('Выберите город для поиска');
            return false;
        } else if (!$inputRubricSearch.val()) {
            toastr['error']('Выберите вид деятельности для поиска');
            return false;
        } else {
            var result = getSearch(selectCity.val(), $inputRubricSearch.val(), 'rubric');
            console.log(selectCity.val(), $inputRubricSearch.text(), result);
            if (result) {
                $tabs.find('a:last').tab('show');
            } else {
                alert('По вашему запросу ничего не найдено.');
            }
        }
    });
})(jQuery);

function getSearch(text, city, type) {
    return true;
}

function getParamByName(name) {
    name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
    var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
        results = regex.exec(script_src);
    return results === null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
}