var self = document.querySelector('script[data-name="amocrm"]'),
    script_src = self.getAttribute('src'),
    search_text = '',
    search_city = 0,
    search_result = false;

(function($){
    var $inputRubricSearch = $('#inputRubricSearch'),
        $inputTextSearch = $('#inputTextSearch'),
        $formTextSearch = $('#formTextSearch'),
        $formRubricSearch = $('#formRubricSearch'),
        $resultTable = $('#resultTable'),
        $gototab = $('.gototab'),
        $tabs = $('#main-tabs'),
        $selectAll = $('#selectAllID');

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
        var selectCity = $(this).find('[name="selectSearchCity"]');
        if (!selectCity.val()) {
            toastr['error']('Выберите город для поиска');
            return false;
        } else if (!$inputTextSearch.val()) {
            toastr['error']('Введите текст поиска');
            $inputTextSearch.focus();
            return false;
        } else {
            var _result = false;
            if (search_text != $inputTextSearch.val()) {
                _result = getSearch(selectCity.val(), $inputTextSearch.val(), 1, 'text');
                search_text = $inputTextSearch.val();
                search_city = selectCity.val();
                search_result = _result;
            } else {
                _result = search_result;
            }
            if (_result) {
                $tabs.find('a:last').tab('show');
            } else {
                toastr['error']('По вашему запросу ничего не найдено. Попробуйте изменить условия поиска.');
            }
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
            var _result = false;
            if (search_text != $inputRubricSearch.find(':selected').text()) {
                _result = getSearch(selectCity.val(), $inputRubricSearch.find(':selected').text(), 1, 'rubric');
                search_text = $inputRubricSearch.find(':selected').text();
                search_city = selectCity.val();
                search_result = _result;
            } else {
                _result = search_result;
            }
            if (_result) {
                $tabs.find('a:last').tab('show');
            } else {
                toastr['error']('По вашему запросу ничего не найдено. Возможно компаний, занимающиеся данным видом деятельности в выбранном городе, нет.');
            }
        }
    });

    $selectAll.click(function() {
        var checkedStatus = this.checked;
        $resultTable.find('tbody tr td:first :checkbox').each(function() {
            $(this).prop('checked', checkedStatus);
        });
    });

    function getSearch(text, city, page, type) {
        var _return = false,
            requestURI = '/getDataSearch/';
        var formData = {
            searchAPI: getParamByName('apikey'),
            searchDomain: getParamByName('subdomain') + '.amocrm.ru',
            searchCity: city,
            searchPage: page
        };
        if (type == 'rubric') {
            formData.searchRubric = text;
            requestURI = '/getDataSearchRubric/';
        } else {
            formData.searchText = text;
        }
        $.post(requestURI, formData, function (data) {

        });
        return _return;
    }
})(jQuery);

function getParamByName(name) {
    name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
    var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
        results = regex.exec(script_src);
    return results === null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
}