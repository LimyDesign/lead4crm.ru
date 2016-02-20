var me = document.querySelector('script[data-name="cabinet"]'),
    me_src = me.getAttribute('src'),
    totalChecked = 0,
    totalFind = 0,
    qty = 0,
    ii = null,
    connected = false,
    contract = getParamByName('c'),
    new_contract = '',
    icq_uin_start = 0,
    icq_uin_change = 0,
    icq_notify_start = '',
    icq_notify_change = '',
    sms_phone_start = 0,
    sms_phone_change = 0,
    sms_notify_start = '',
    sms_notify_change = '',
    email_address_start = '',
    email_address_change = '',
    email_notify_start = '',
    email_notify_change = '';

$(document).ready(function() 
{
  /* Меняем приветствие в зависимости от времени суток у пользователя. А также подгружаем
     и показываем данные пользователя о текущем балансе и тарифе.
  ========================================================================================= */
  greeting('#greeting');
  $('#kk_help').tooltip({
    placement: 'auto'
  });
  $('[data-toggle="tooltip"]').tooltip({
    placement: 'auto'
  });
  $.post('/getUserData/', function(data) {
    $('#balance').number(data.balance, 2, '.', ' ');
    $('#tariff').text(data.tariff);
    $('#qty').number(data.qty, 0, '.', ' ');
    qty = data.qty;
  }, 'json');

  /* Показываем пользователю запрос на принятие контракта
  ========================================================================================= */
  if (contract == 'f' && new_contract == '') {
    $('#termsModal').modal({
      backdrop: 'static',
      keyboard: false,
      show: true
    });
  }

  /* Обрабатываем действия пользователя по принятию/непринятию контракта
  ========================================================================================= */
  $('#termsModal #decline').click(function() {
    $('#termsModal').find('button').attr('disabled', 'disabled');
    $.post('/contract/', { decision: false }).done(function() {
      window.location = '/logout/';
    });
  });
  $('#termsModal #accept').click(function() {
    $('#termsModal').find('button').attr('disabled', 'disabled');
    $.post('/contract/', { decision: true }, function(data) {
      new_contract = data;
    }, 'html').done(function() {
      $('#termsModal').modal('hide');
    });
  });


  /* Мастер экспорта данных из 2ГИС
  ========================================================================================= */
  var current_step = 1;
  $('#wizard-form').submit(function(event) {
    event.preventDefault();
    var form_action = $(this).attr('action');
    var crm_id = $(this).find('#selectCRM').val();
    var city = $(this).find('#selectCity').val();
    var searchType = $(this).find('input[name="searchType"]:checked').val();
    var wzd_pos = $('.wizard').offset(),
        wzd_height = $('.wizard').height();
    $(this).find('fieldset').attr('disabled', 'disabled');
    $('.overlay').css({ 'top': wzd_pos.top, 'left': wzd_pos.left }).height(wzd_height).fadeIn('fast');

    if (form_action != '/showSearchDialog/') {
      if ($('#ii').hasClass('hide') === false)
        $('#ii').addClass('hide');
      if ($('#ii_help').hasClass('hide') === false)
        $('#ii_help').addClass('hide');
    }
    
    if (form_action == '/step-1/') {
      $('.overlay').fadeOut();
      $(this).find('fieldset').removeAttr('disabled');
      $(this).attr('action', '/step-2/');
      $('#wizard-helper #step-'+current_step).fadeOut('fast', function() {
        $('#wizard-helper #step-1').fadeIn('fast');
      });
      $('#wizard-form #step-'+current_step).fadeOut('fast', function() {
        $('#wizard-form #step-1').fadeIn('fast');
      });
    } else if (form_action == '/step-2/') {
      if (crm_id != null) {
        localStorage.setItem('crm_id', crm_id);
        $.post(form_action, {crm_id: crm_id}, function(data) {
          $('.overlay').fadeOut();
          $('#wizard-form').find('fieldset').removeAttr('disabled');
          if (data.error == '0') {
            if (data.module) {
              renderSelections(9999);
              current_step = 9999;
              $('#wizard-form').attr('action', '/step-1/');
              $('#wizard-helper #step-1').fadeOut('fast', function() {
                $('#wizard-helper #step-9999').fadeIn('fast');
              });
              $('#wizard-form #step-1').fadeOut('fast', function() {
                $('#wizard-form #step-9999').find('#module-link').attr('href', data.module);
                $('#wizard-form #step-9999').find('#module-name').text(data.name);
                $('#wizard-form #step-9999').fadeIn('fast');
              });
            } else {
              current_step = 2;
              ii = data.ii;
              $('#wizard-form').attr('action', '/step-3/');
              $('#wizard-helper #step-1').fadeOut('fast', function() {
                $('#wizard-helper #step-2').fadeIn('fast');
              });
              $('#wizard-form #step-1').fadeOut('fast', function() {
                $('#wizard-form #step-2').fadeIn('fast');
              });
            }
          }
        }, 'json');
      } else {
        $('.overlay').fadeOut();
        $('#wizard-form').find('fieldset').removeAttr('disabled');
      }
    } else if (form_action == '/step-3/') {
      if (city != null) {
        localStorage.setItem('city_id', city);
        $('.overlay').fadeOut();
        $('#wizard-form').find('fieldset').removeAttr('disabled');
        current_step = 3;
        $('#wizard-form').attr('action', '/step-4/');
        $('#wizard-helper #step-2').fadeOut('fast', function() {
          $('#wizard-helper #step-3').fadeIn('fast');
        });
        $('#wizard-form #step-2').fadeOut('fast', function() {
          $('#wizard-form #step-3').fadeIn('fast');
        });
      } else {
        $('.overlay').fadeOut();
        $('#wizard-form').find('fieldset').removeAttr('disabled');
      }
    } else if (form_action == '/step-4/') {
      if (searchType != null) {
        if (ii != null) {
          $('#ii').removeClass('hide');
          $('#ii_help').removeClass('hide');
          $.post('/getIntegrated/', { ii: ii }, function (data) {
            $('#ii_html').html(data.html);
            connected = data.connected;
          }, 'json').done(function() {
            $('.overlay').fadeOut();
            localStorage.setItem('search_id', searchType);
            renderSelections(4);
            $('#wizard-form').find('fieldset').removeAttr('disabled');
            current_step = 4;
            $('#wizard-form').attr('action', '/showSearchDialog/');
            $('#wizard-helper #step-3').fadeOut('fast', function() {
              $('#wizard-helper #step-4').fadeIn('fast');
            });
            $('#wizard-form #step-3').fadeOut('fast', function() {
              $('#wizard-form #step-4').fadeIn('fast');
            });
          });
        } else {
          $('.overlay').fadeOut();
          localStorage.setItem('search_id', searchType);
          renderSelections(4);
          $('#wizard-form').find('fieldset').removeAttr('disabled');
          current_step = 4;
          $('#wizard-form').attr('action', '/showSearchDialog/');
          $('#wizard-helper #step-3').fadeOut('fast', function() {
            $('#wizard-helper #step-4').fadeIn('fast');
          });
          $('#wizard-form #step-3').fadeOut('fast', function() {
            $('#wizard-form #step-4').fadeIn('fast');
          });
        }
        
      }
    } else if (form_action == '/showSearchDialog/') {
      if (searchType == 1) {
        $('#searchDialog-'+searchType).find('dl').empty();
        if (localStorage.getItem('2GISRubrics')) {
          var rubrics = JSON.parse(localStorage.getItem('2GISRubrics'));
          rubricListConstructor(rubrics);
          showSearchDialog(searchType, crm_id, city);
        } else {
          var postData = {
            importAPI: getParamByName('k'),
            importDomain: 'www.lead4crm.ru'
          };
          $.post('/getRubricList/', postData, function (data) {
            if (data.error == 0) {
              if (data.rubrics) {
                localStorage.setItem('2GISRubrics', JSON.stringify(data.rubrics));
                rubricListConstructor(data.rubrics);
              }
            }
          }, 'json').done(function() {
            showSearchDialog(searchType, crm_id, city);
          });
        }
      } else showSearchDialog(searchType, crm_id, city);
    }
  });

  var search_text_prev = '';
  $('#searchDialog-0 form').submit(function(event) {
    event.preventDefault();
    var search_text = $(this).find('#searchInput').val();
    if (search_text != search_text_prev && search_text != '') {
      search_text_prev = search_text;
      renderPage(1, search_text);
    }
  });

  $('[id^=searchDialog-] #ok').click(function() {
    if (qty < totalChecked) {
      alert("Вы указали слишком большое кол-во компаний для импорта.\n\nВы можете импортировать всего: "+qty);
      return;
    } else {
      if (totalChecked > 0) {
        var totalProgress = 0;
        var index = 1;
        var companyID = 0, companyHash = '';
        var progressIcrement = Math.floor(100/totalChecked);
        var $import_button = $(this);
        var $modal_content = $(this).parent().parent();
        var $progressElement = $modal_content.find('.progress');
        var $companyIndex = 0;
        $(this).attr('disabled', 'disabled');
        $progressElement.fadeIn('fast', function() {
          $progressElement
            .find('.progress-bar')
            .addClass('progress-bar-striped')
            .css('width', '0%').text('0%');
          for (index = 1; index <= (totalFind > 50 ? 50 : totalFind); index++) {
            $companyIndex = $modal_content.find('#company'+index);
            if ($companyIndex.prop('checked')) {
              companyID = $companyIndex.attr('name');
              companyHash = $companyIndex.attr('value');
              importCompany(companyID, companyHash, true, function() {
                totalChecked--;
                $progressElement.find('.progress-bar').css('width', function (index, value) {
                  var fWidth = $progressElement.width();
                  totalProgress += progressIcrement;
                  return fWidth / (totalChecked + 1);
                }).text(totalProgress+'%');
                if (totalChecked > 0)
                  $import_button.html('Импортировать отмеченные <notr>('+totalChecked+')</notr>');
                else {
                  $progressElement
                    .find('progress-bar')
                    .removeClass('progress-bar-striped')
                    .css('width', '100%')
                    .text('100%');
                  $import_button.text('Импортировать отмеченные');
                }
              });
            }
          }
          setTimeout(function() {
            $progressElement.fadeOut('slow');
          }, 2000);
        });
      }
    }
  });

  /* Меняем класс активности на кнопках в зависимости от выбранного радио.
  ========================================================================================= */
  $('input[name="searchType"]').click(function() {
    $(this).parent().parent().find('label.active').removeClass('active');
    $(this).parent().addClass('active');
  });

  /* После закрытия диалогового окна поиска разблокируем форму мастера выборок.
  ========================================================================================= */
  $('[id^=searchDialog-]').on('hidden.bs.modal', function (e) {
    renderSelections(4);
    $('.overlay').fadeOut();
    $('#wizard-form').find('fieldset').removeAttr('disabled');
  });

  /* Устанавливаем фокус в поисковой строке ассоциативного поиска, при открытии диалога
     поиска.
  ========================================================================================= */
  $('#searchDialog-0').on('shown.bs.modal', function() {
    var modal = $(this);
    modal.find('input[type="text"]').focus();
  });

  /* Выделяем ключ доступа, чтобы после авторизации и по клику по нему можно было бы сразу
     копировать ключ полностью и без погрешности ошибки при выделении пользователем
     самостоятельно.
  ========================================================================================= */
  $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
    var target = $(e.target).attr('href').substring(1);
    if (target == 'tabAPIKey')
      selectText('apikey');
    else if (target == 'tabNotification')
      getSMSInfo('79041326000');
    else if (target == 'tabReferal')
      getReferalInfo();
  });
  $('body').on('mouseup', '.well', function() {
    selectText('apikey');
  });

  /* Меняем уникальный ключ доступа и показываем новый без перезагрузки страницы.
  ========================================================================================= */
  $('body').on('click', '#newapikey', function() {
    var b = $(this);
    b.addClass('disabled');
    b.find('i.fa').removeClass('fa-key');
    b.find('i.fa').addClass('fa-spinner fa-pulse');
    $.get('/newAPIKey/', function(data) {
      $('#apikey').text(data);
    }).done(function() {
      b.removeClass('disabled');
      b.find('i.fa').removeClass('fa-spinner fa-pulse');
      b.find('i.fa').addClass('fa-key');
      selectText('apikey');
    });
  });

  /* Выбираем соответствующий метод оплаты. Это не совсем стандартный список. Потребовалось
     так извратиться из-за внедрения картинок в список выбора метода оплаты.
  ========================================================================================= */
  $('body').on('click', '.option li', function() {
    var i = $(this).parents('.select').attr('id');
    var v = $(this).children().html();
    var o = $(this).attr('id');
    $('#'+i+' .selected').attr('id',o);
    $('input[name="paymentType"]').val(o);
    $('#'+i+' .selected').html(v);
  });

  /* Проверяем сумму оплаты. Не позволяем сработать форме до момента пока пользователь не
     введет требуемую минимальную сумму. Остальные ограничения по суммам смотрит сама
     платежная система.
  ========================================================================================= */
  $('#yaform').submit(function() {
    $('#inputSum').number(true, 2, '.', '');
    if ($('#inputSum').val() >= 1) {
      return true;
    } else {
      $('#inputSum').number(true, 2, '.', ' ');
      return false;
    }
  });
  $('#inputSum').number(true, 2, '.', ' ');
  $('#inputInvoiceSum').number(true, 2, '.', ' ');

  /* Изменяем тарифный план пользователя, но с обязательным подтверждением, иначе может
     случиться ай-я-яй.
  ========================================================================================= */
  $('#tariffForm').submit(function(e) {
    e.preventDefault();
    if ($('select[name="tariff"]').val() != 'demo') {
      var a = $(this).attr('action');
      var s = $(this).serialize();
      $('#tariffModal').modal('show');
      $('#tariffModal').find('#ok').on('click', function() {
        var b = $(this)
        var t = b.text();
        b.addClass('disabled');
        b.html('<i class="fa fa-fw fa-spinner fa-pulse"></i> ' + t);
        $.post(a,s,function(data) {
          $('#qty').number(data.qty, 0, '.', ' ');
          $('#balans').number(data.balans, 2, '.', ' ');
          $('#tariff').text(data.tariff);
        }, 'json').done(function() {
          $('#tariffModal').modal('hide');
          b.removeClass('disabled');
          b.text(t);
        });
      });
    }
  });

  /* Прописываем изначальные и конечные значения ICQ UIN.
     Необходимо для дальнейшей проверки сделанных изменений.
  ========================================================================================= */
  icq_uin_start = icq_uin_change = $('#inputICQUIN').val();
  $('#inputICQUIN').change(function() {
      icq_uin_change = $(this).val();
  });

  /* Прописываем изначальные и конечные значения настроек уведомлений для ICQ.
     Необходимо для дальнейшей проверки сделанных изменений.
  ========================================================================================= */
  $('#formICQSetting input[name^="notify"]:checked').each(function() {
    icq_notify_start += $(this).val();
    icq_notify_change = icq_notify_start;
  });
  $('#formICQSetting input[name^="notify"]').change(function() {
    icq_notify_change = '';
    $('#formICQSetting input[name^="notify"]:checked').each(function() {
      icq_notify_change += $(this).val();
    });
  });

  /* Выполняем настройку клиента для связи с клиентской ICQ.
  ========================================================================================= */
  $('#formICQSetting').submit(function (e) {
    e.preventDefault();
    var uuin = $('#inputICQUIN').val(),
        code = $('#inputICQCode').val(),
        $fieldset = $(this).find('fieldset'),
        serialized = $(this).serialize();
    $fieldset.attr('disabled', 'disabled');
    if (isInt(uuin) && uuin != '') {
      if (uuin != getParamByName('icq') && code == '') {
        sendCode(uuin);
        $('#resendICQCode').attr('onclick', 'sendCode('+uuin+')');
        if ($('#groupICQUIN').hasClass('has-error')) {
          $('#groupICQUIN').removeClass('has-error');
        }
        $('#groupICQUIN').addClass('has-success');
        $('#groupICQCode').removeClass('hide');
        scrollTo('#inputICQCode');
        $fieldset.removeAttr('disabled');
        $('#inputICQCode').focus();
      } else {
        if (icq_uin_start != icq_uin_change || icq_notify_start != icq_notify_change) {
          $.post('/icq/save/', serialized, function (data) {
            if (data == 'error_code') {
              $('#groupICQCode').addClass('has-error');
              scrollTo('#groupICQCode');
              $fieldset.removeAttr('disabled');
              $('#inputICQCode').focus();
            } else {
              icq_uin_start = icq_uin_change;
              icq_notify_start = icq_notify_change;
              $fieldset.removeAttr('disabled');
              if ($('#groupICQUIN').hasClass('has-success')) {
                $('#groupICQUIN').removeClass('has-success');
              }
              if ($('#groupICQUIN').hasClass('has-error')) {
                $('#groupICQUIN').removeClass('has-error');
              }
              $('#groupICQCode').addClass('hide');
              $('#statusICQSetting').removeClass('hide');
              scrollTo('#statusICQSetting');
              setTimeout(function() {
                $('#statusICQSetting').addClass('hide');
              }, 5000);
            }
          });
        } else {
          $fieldset.removeAttr('disabled');
        }
      }
    } else {
      $('#groupICQUIN').addClass('has-error');
      $fieldset.removeAttr('disabled');
      scrollTo('#groupICQUIN');
      $('#inputICQUIN').focus();
    }
  });

  /* Прописываем изначальные и конечные значения телефона для SMS.
     Необходимо для дальнейшей проверки сделанных изменений.
  ========================================================================================= */
  sms_phone_start = sms_phone_change = $('#inputSMSPhone').val();
  $('#inputSMSPhone').change(function() {
      sms_phone_change = $(this).val();
      getSMSInfo(sms_phone_change);
  });

  /* Прописываем изначальные и конечные значения настроек уведомлений для SMS.
     Необходимо для дальнейшей проверки сделанных изменений.
  ========================================================================================= */
  $('#formSMSSetting input[name^="notify"]:checked').each(function() {
    sms_notify_start += $(this).val();
    sms_notify_change = sms_notify_start;
  });
  $('#formSMSSetting input[name^="notify"]').change(function() {
    sms_notify_change = '';
    $('#formSMSSetting input[name^="notify"]:checked').each(function() {
      sms_notify_change += $(this).val();
    });
  });

  $('#refreshSMSUser').click(function(e) {
    e.preventDefault();
    var phone = $('#inputSMSPhone').val();
    var text = $(this).text(), i = 0;
    var loading = setInterval(function() {
      if (i < 3) {
        $('#refreshSMSUser').append('.');
        i++;
      } else {
        i = 0;
        $('#refreshSMSUser').text(text);
      }
    }, 200);
    getSMSInfo(phone, function() {
      clearInterval(loading);
      $('#refreshSMSUser').text(text);
    });
  });

  /* Выполняем привязку мобильного телефона для SMS уведомлений.
  ========================================================================================= */
  $('#formSMSSetting').submit(function (e) {
    e.preventDefault();
    var phone = $('#inputSMSPhone').val(),
        code = $('#inputSMSCode').val(),
        $fieldset = $(this).find('fieldset'),
        serialized = $(this).serialize();
    $fieldset.attr('disabled', 'disabled');
    if (isPhone(phone)) {
      getSMSInfo(phone, function(result, cost) {
        if (result === false) {
          return;
        } else {
          if (phone != getParamByName('sms') && code == '') {
            cost = cost.toFixed(2);
            var isSendSMS = confirm('Стоимость подтверждения номера будет составлять: '+cost+" руб.\nВы согласны?");
            if (isSendSMS) {
              sendSMSCode(phone);
              $('#resendSMSCode').attr('onclick', 'sendSMSCode('+phone+')');
              if ($('#groupSMSPhone').hasClass('has-error')) {
                $('#groupSMSPhone').removeClass('has-error');
              }
              $('#groupSMSPhone').addClass('has-success');
              $('#groupSMSCode').removeClass('hide');
              scrollTo('#inputSMSCode');
              $fieldset.removeAttr('disabled');
              $('#inputSMSCode').focus();
            } else {
              $fieldset.removeAttr('disabled');
              return;
            }
          } else {
            if (sms_phone_start != sms_phone_change || sms_notify_start != sms_notify_change) {
              $.post('/sms/save/', serialized, function (data) {
                if (data.error == 100) {
                  $('#groupSMSCode').addClass('has-error');
                  scrollTo('#groupSMSCode');
                  $fieldset.removeAttr('disabled');
                  $('#inputSMSCode').focus();
                } else if (data.error == 200) {
                  scrollTo('#alertSMSUser');
                  $('#alertSMSUser').removeClass('hide');
                } else {
                  sms_phone_start = sms_phone_change;
                  sms_notify_start = sms_notify_change;
                  $fieldset.removeAttr('disabled');
                  if ($('#groupSMSPhone').hasClass('has-success')) {
                    $('#groupSMSPhone').removeClass('has-success');
                  }
                  if ($('#groupSMSPhone').hasClass('has-error')) {
                    $('#groupSMSPhone').removeClass('has-error');
                  }
                  $('#groupSMSCode').addClass('hide');
                  $('#statusSMSSetting').removeClass('hide');
                  scrollTo('#statusSMSSetting');
                  setTimeout(function() {
                    $('#statusSMSSetting').addClass('hide');
                  }, 5000);
                }
              }, 'json');
            } else {
              $fieldset.removeAttr('disabled');
            }
          }
        }
      })
    } else {
      $('#groupSMSPhone').addClass('has-error');
      $fieldset.removeAttr('disabled');
      scrollTo('#groupSMSPhone');
      $('#inputSMSPhone').focus();
    }
  });

  /* Прописываем изначальные и конечные значения телефона для SMS.
     Необходимо для дальнейшей проверки сделанных изменений.
  ========================================================================================= */
  email_address_start = email_address_change = $('#inputEmailAddress').val();
  $('#inputEmailAddress').change(function() {
      email_address_change = $(this).val();
  });

  /* Прописываем изначальные и конечные значения настроек уведомлений для SMS.
     Необходимо для дальнейшей проверки сделанных изменений.
  ========================================================================================= */
  $('#formEmailSetings input[name^="notify"]:checked').each(function() {
    email_notify_start += $(this).val();
    email_notify_change = email_notify_start;
  });
  $('#formEmailSetings input[name^="notify"]').change(function() {
    email_notify_change = '';
    $('#formEmailSetings input[name^="notify"]:checked').each(function() {
      email_notify_change += $(this).val();
    });
  });

  /* Выполняем привязку мобильного телефона для SMS уведомлений.
  ========================================================================================= */
  $('#formEmailSetings').submit(function (e) {
    e.preventDefault();
    var email = $('#inputEmailAddress').val(),
        $fieldset = $(this).find('fieldset'),
        serialized = $(this).serialize();
    $fieldset.attr('disabled', 'disabled');
    if (isEmail(email)) {
      if ($('#groupEmailAddress').hasClass('has-error')) {
        $('#groupEmailAddress').removeClass('has-error');
      }
      $('#groupEmailAddress').addClass('has-success');

      if (email != getParamByName('email')) {
        sendEmail('code', serialized, function() {
          if ($('#groupEmailAddress').hasClass('has-success')) {
            $('#groupEmailAddress').removeClass('has-success');
          }
          $('#statusEmailCode').removeClass('hide');
          scrollTo('#statusEmailCode');
        });
      } else {
        if (email_address_start != email_address_change || email_notify_start != email_notify_change) {
          sendEmail('change', serialized, function() {
            email_address_start = email_address_change;
            email_notify_start = email_notify_change;
            if ($('#groupEmailAddress').hasClass('has-success')) {
              $('#groupEmailAddress').removeClass('has-success');
            }
            $('#statusEmailSetting').removeClass('hide');
            scrollTo('#statusEmailSetting');
            setTimeout(function() {
              $('#statusEmailSetting').addClass('hide');
            }, 5000);
            $fieldset.removeAttr('disabled');
          });
        } else {
          $fieldset.removeAttr('disabled');
        }         
      }
    } else {
      $('#groupEmailAddress').addClass('has-error');
      $fieldset.removeAttr('disabled');
      scrollTo('#groupEmailAddress');
      $('#inputEmailAddress').focus();
    }
  });

  /* Выполняем запрос на сохранение данных для реферальной программы пользователя.
  ========================================================================================= */
  $('#referalForm').submit(function(e){
    e.preventDefault();
    var $firm_name = $(this).find('#inputFirmName'),
        $firm_name_val = $firm_name.val(),
        $inn = $(this).find('#inputINN'),
        $inn_val = $inn.val(),
        $bik = $(this).find('#inputBIK'),
        $bik_val = $bik.val(),
        $rs = $(this).find('#inputRS'),
        $rs_val = $rs.val(),
        _fieldset = $(this).find('fieldset'),
        _action = $(this).attr('action'),
        _header = $('header'),
        _alert = $('#refMessageSuccess'),
        dataForm = {
          firm: $firm_name_val,
          inn: $inn_val,
          bik: $bik_val,
          rs: $rs_val
        };
    if (!$firm_name_val || !$inn_val || !$bik_val || !$rs_val) {
      $.growl.error({title: 'Ошибка!', message: 'Заполните все поля формы!'});
    } else {
      if (checkINN($inn_val)) {
        if ($bik_val.length == 9) {
          var bik_search_url = '/getBIKInfo/?bik=' + $bik_val;
          _fieldset.attr('disabled', 'disabled');
          $.get(bik_search_url, function(data) {
            if (data.error) {
              _fieldset.removeAttr('disabled');
              $bik.focus();
              $.growl.error({ title: 'Ошибка!', message: 'По вашему БИК не найден ни один банк.'});
            } else {
              if (checkRS($rs_val, $bik_val)) {
                dataForm.ks = data.ks;
                dataForm.bank = data.name + ' в г. ' + data.city;
                $.post(_action, dataForm).done(function() {
                  $.growl.notice({ title: "УРА!", message: "Ваша заявка успешно отправлена." });
                  var _alert_pos = _alert.position();
                  _alert.removeClass('hide').animate({ scrollTop: _alert_pos.top + _header.height + 20}, 'fast');
                });
              } else {
                _fieldset.removeAttr('disabled');
                $rs.focus();
                $.growl.error({ title: 'Ошибка!', message: 'Расчетный счет указан не правильно.' });
              }
            }
          }, 'json');
        } else {
          $bik.focus();
          $.growl.error({ title: 'Ошибка!', message: 'БИК должен состоять только из цифр и быть длинной 9 цифр.' });
        }
        $.get()
      } else {
        $inn.focus();
        $.growl.error({title: 'Ошибка!', message: 'Неправильный ИНН. Введите верный ИНН.'});
      }
    }
  });
});

/* Функция приветствия пользователя в зависимости от времени на компьютере пользователя.
========================================================================================= */
function greeting(element) {
  var d = new Date(),
    h = d.getHours(),
    g = $(element);
  if (h >= 5 && h < 12)
    g.text('Доброе утро!');
  else
  {
    if (h >= 12 && h < 18)
      g.text('Добрый день!');
    else
    {
      if (h >= 18 && h < 24)
        g.text('Добрый вечер!');
      else
        g.html('Доброй ночи&hellip;');
    }
  }
}

/* Функция проверки ИНН.
 ========================================================================================= */
function checkINN(num) {
  num = "" + num;
  num = num.split('');
  if ((num.length == 10) && (num[9] == ((2 * num[0] + 4 * num[1] + 10 * num[2] + 3 * num[3] + 5 * num[4] + 9 * num[5] + 4 * num[6] + 6 * num[7] + 8 * num[8] % 11) % 10)))
      return true;
  else if ((num.length == 12) && ((num[10] == ((7 * num[0] + 2 * num[1] + 4 * num[2] + 10 * num[3] + 3 * num[4] + 5 * num[5] + 9 * num[6] + 4 * num[7] + 6 * num[8] + 8 * num[9]) % 11) % 10) && (num[11] == ((3 * num[0] + 7 * num[1] + 2 * num[2] + 4 * num[3] + 10 * num[4] + 3 * num[5] + 5 * num[6] + 9 * num[7] + 4 * num[8] + 6 * num[9] + 8 * num[10]) % 11) % 10)))
      return true;
  else
      return false;
}

/* Функция проверки расчетного счета.
 ========================================================================================= */
function checkRS(account, bik) {
  return checkBankAccount(bik.substr(6,3) + account);
}

/* Функция проверки правильности указания банковского счёта.
 ========================================================================================= */
function checkBankAccount(str) {
  var result = false;
  var sum = 0;
  var v = [7,1,3,7,1,3,7,1,3,7,1,3,7,1,3,7,1,3,7,1,3,7,1];
  for (var i = 0; i <= 22; i++) {
    sum = sum + (Number(str.charAt(i)) * v[i]) % 10;
  }
  if (sum % 10 == 0)
      result = true;
  return result;
}

/* Функция прокрутки страницы к конкретному элементу.
========================================================================================= */
function scrollTo(element) {
  $('html, body').animate({ scrollTop: $(element).offset().top - $('#header').outerHeight() - 20 }, 1000);
}

/* Функция перехода к предыдущему шагу в мастере выборок.
========================================================================================= */
function wizardStep(step) {
  var curr_step = step + 1;
  
  if ($('#ii').hasClass('hide') === false)
    $('#ii').addClass('hide');
  if ($('#ii_help').hasClass('hide') === false)
    $('#ii_help').addClass('hide');

  $('#wizard-form').attr('action', '/step-'+curr_step+'/');
  $('#wizard-helper #step-'+curr_step).fadeOut('fast', function() {
    $('#wizard-helper #step-'+step).fadeIn('fast');
  });
  $('#wizard-form #step-'+curr_step).fadeOut('fast', function() {
    $('#wizard-form #step-'+step).fadeIn('fast');
  });
}

/* Функция составления архивы выборок.
========================================================================================= */
function renderSelections(step) {
  var crm_id = localStorage.getItem('crm_id');
  $.post('/getUserCache/', function (data) {
    var usDate, uspDate,
        date = new Date(),
        date = date.getFullYear() + '-' + ('0' + (date.getMonth() + 1)).slice(-2),
        $list = $('#wizard-form #step-'+step).find('div.list-group');
    $list.empty();
    for (var i in data) {
      usDate = data[i].addtime.substring(0,7);
      if (usDate != uspDate) {
        if (usDate == date) {
          $list.append('<a href="#." class="list-group-item active" onclick=downloadSelection("'+usDate+'",'+crm_id+')>Скачать выборку за '+usDate+'</a>');
        } else {
          $list.append('<a href="#." class="list-group-item" onclick=downloadSelection("'+usDate+'",'+crm_id+')>Скачать выборку за '+usDate+'</a>');
        }
        uspDate = usDate;
      }
    }
  });
}

/* Функция загрузки уже имеющихся выборок.
========================================================================================= */
function downloadSelection(sDate, crm_id) {
  if (connected) {
    var exportToCRM = confirm("Начать экспорт в вашу CRM?\n\nЕсли вы хотите скачать файл, то отключитесь от CRM, нажав на соответствующую красную кнопку.");
    if (exportToCRM) {
      exportSelection(sDate, crm_id);
    }
  } else {
    var downloadWindow = window.open('/getSelection/'+sDate+'/?crm_id='+crm_id);
    window.setTimeout(function() {
      downloadWindow.close();
    }, 10000);
  }
}

function exportSelection(sDate, crm_id, post, increment, data) {
  post = typeof post !== 'underfined' ? post : false;
  increment = typeof increment !== 'underfined' ? increment : 0;
  data = typeof data !== 'underfined' ? data : null;
  
  var exportDialog = $('#exportDialog');

  if (post) {
    var percent = Math.round((increment + 1) * 100 / data.total);
    if (increment  == data.total) {
      setTimeout(function() {
        exportDialog.modal('hide');
      }, 2000);
    } else {
      $.post('/crmPostCompany/'+ii+'/', { opt: data.opt[increment] }, function (res) {
        if (res) {
          if (res.status.code == 'error') {
            alert(res.status.message);
          } else if (res.status.code == 'warning') {
            exportDialog.find('#status').css('color', '#d43f3a').text(' дубликат.');
          } else {
            exportDialog.find('#status').css('color', '#4cae4c').text(' добавлена.');
          }
        } else {
          exportDialog.modal('hide');
        }
      }, 'json').done(function() {
        exportDialog.find('.progress-bar').attr('aria-valuenow', percent);
        exportDialog.find('.progress-bar').css('width', percent+'%');
        exportDialog.find('.progress-bar').text(percent+'%');
        exportDialog.find('#companyName').text(data.opt[increment].name);
        increment++;
        exportSelection(sDate, crm_id, true, increment, data);
      });
    }
  } else {
    exportDialog.find('.progress-bar').attr('aria-valuenow', '0');
    exportDialog.find('.progress-bar').css('width', '0');
    exportDialog.find('.progress-bar').text('0%');
    exportDialog.find('#companyName').text('n/a');
    
    exportDialog.modal({
      backdrop: false,
      keyboard: false,
      show: true
    });

    $.post('/getSelectionArray/'+sDate+'/', { crm_id: crm_id, json: true, addon: true }, function (data) {
      exportSelection(sDate, crm_id, true, 0, data);
    }, 'json');
  }
}

/* Функция показа диалогов поиска.
========================================================================================= */
function showSearchDialog(number) {
  $('#searchDialog-'+number).modal({
    backdrop: false
  });
}

/* Функция-конструктор показывающая все рубрики, распределенные по вложенности.
========================================================================================= */
function rubricListConstructor(rubrics) {
  var level2 = null,
      level3 = null;
  rubrics.forEach(function (entry) {
    if (!entry.parent)
    {
      $('#searchDialog-1').find('dl').append('<dt><a href="#." title="'+entry.name+'" id="r-'+entry.id+'" onclick="searchByID('+entry.id+')">'+entry.name+'</a></dt>');
      level2 = entry.id;
    } else if (level2 == entry.parent) {
      $('#searchDialog-1').find('dl').append('<dd id="'+entry.id+'"><a href="#." class="btn btn-xs btn-primary" id="r-'+entry.id+'" onclick="searchByID('+entry.id+')">'+entry.name+'</a></dd>');
      level3 = entry.id;
    } else if (level3 == entry.parent) {
      $('#searchDialog-1').find('dl dd#'+level3).append(' <a href="#." class="btn btn-xs btn-default" id="r-'+entry.id+'" onclick="searchByID('+entry.id+')">'+entry.name+'</a>');
    }
  });
}

function searchByID(rubric_id) {
  if (rubric_id == 0) {
    $('#searchDialog-1 #ok').attr('disabled', 'disabled');
    $('#searchDialog-1 #helper').empty().html('Кликните по любому виду деятельности. <em>Это не страшно.</em> &#128521;');
    $('#searchDialog-1').find('#searchResult').hide(400, function() {
      $('.dl-horizontal').show();
    });
  } else {
    var search_text = $('#searchDialog-1').find('#r-'+rubric_id).text();
    $('#searchDialog-1 #helper').empty().html('Искомый вид деятельности: <div class="btn-group btn-group-xs" role="group" aria-label="..."><button type="button" class="btn btn-invert">'+search_text+'</button><button type="button" class="btn btn-danger" onclick="searchByID(0)">&times;</button>');
    renderPage(1, search_text, function() {
      $('.dl-horizontal').hide(400, function() {
        $('#searchDialog-1').find('#searchResult').show();
      });
    });
  }
}

/* Функция выбора всех компаний разом.
========================================================================================= */
function chMarkAll(sdn) {
  var checkedStatus = $('#searchDialog-'+sdn).find('#selectAllID:checked').length;
  var batchImport = $('#searchDialog-'+sdn).find('#ok');
  console.log(checkedStatus);
  if (!checkedStatus) {
    totalChecked = 0;
    batchImport.attr('disabled', 'disabled').html('Импортировать отмеченные');
  } else {
    if (totalFind > 50)
      totalChecked = 50;
    else
      totalChecked = totalFind;
    batchImport.removeAttr('disabled').html('Импортировать отмеченные <notr>('+totalChecked+')</notr>');
  }
  $('#searchDialog-'+sdn+' tbody tr').find('td:first :checkbox').each(function() {
    $(this).prop('checked', checkedStatus);
  });
}

/* Функция построения страницы списка компаний.
========================================================================================= */
function renderPage(pageNum, search_text, callback) {
  totalChecked = 0;
  var formData = {
    searchAPI: getParamByName('k'),
    searchDomain: 'www.lead4crm.ru',
    searchCity: localStorage.getItem('city_id'),
    searchPage: pageNum
  };

  var requestURL = '',
      type = localStorage.getItem('search_id'),
      companyList;

  $.post('/getUserCache/', function (data) {
    companyList = data;
  });

  $('#searchDialog-'+type+' #page'+pageNum).html('<span><i class="fa fa-circle-o-notch fa-spin"></i></span>');
  $('#searchDialog-'+type+' tbody').find('tr').remove().append('<tr class="notr"><td colspan="5" class="text-center"><i class="fa fa-fw fa-spinner fa-pulse"></i> Построение списка компаний&hellip;</td></tr>');

  if (type == 1) {
    formData.searchRubric = search_text;
    requestURL = '/getDataSearchRubric/';
  } else {
    formData.searchText = search_text;
    requestURL = '/getDataSearch/';
  }
  $.post(requestURL, formData, function (data) {
    totalFind =  data.total;
    $('#qty').number(data.qty, 0, '.', ' ');
    $('#searchDialog-'+type).find('.btn-invert').text(search_text+' ('+data.total+')');
    $('#searchDialog-'+type).find('.pagination').empty();
    $('#searchDialog-'+type+' tbody').find('tr').remove();
    if (pageNum == 1) {
      $('#searchDialog-'+type+' .pagination').append('<li class="active" id="page1"><span>1 <span class="sr-only">(текущая)</span></span></li>');
      if (totalFind > 50) {
        var page = 2;
        for (var i = 50; i < totalFind; i = i + 50) {
          $('#searchDialog-'+type+' .pagination').append('<li id="page'+page+'"><a href="#." onclick="renderPage('+page+',\''+search_text+'\')">'+page+'</a></li>');
          page++;
        }
      }
    } else {
      if (totalFind > 50) {
        var page = 1;
        for (var i = 0; i < totalFind; i = i + 50) {
          if (page == pageNum) {
            $('#searchDialog-'+type+' .pagination').append('<li class="active" id="page'+page+'"><span>'+page+' <span class="sr-only">(текущая)</span></span></li>');
          } else {
            $('#searchDialog-'+type+' .pagination').append('<li id="page'+page+'"><a href="#." onclick="renderPage('+page+',\''+search_text+'\')">'+page+'</a></li>');
          }
          page++;
        }
      }
    }
    var user_cache = null,
        statusExist = 0,
        oldExist = 0,
        addTime,
        d = new Date(),
        d = d.getFullYear()+'-'+('0'+(d.getMonth()+1)).slice(-2);
    data.result.forEach(function (entry, index) {
      var cId = index + 1;
      for (var i in companyList) {
        if (companyList[i].id == entry.id && companyList[i].addtime.substring(0,7) == d) {
          statusExist = 1;
          oldExist = 0;
          addTime = companyList[i].addtime.substring(0,10);
          break;
        } else if (companyList[i].id == entry.id && companyList[i].addtime.substring(0,7) != d) {
          statusExist = 0;
          oldExist = 1;
          addTime = companyList[i].addtime.substring(0,10);
          break;
        } else {
          statusExist = 0;
          oldExist = 0;
        }
      }
      if (statusExist) {
        $('#searchDialog-'+type).find('tbody').append('<tr class="notr success"><td class="text-center"><input type="checkbox" name="'+entry.id+'" value="'+entry.hash+'" onclick=changeMark("'+entry.id+'") id="company'+cId+'" disabled></td><td>'+entry.name+'</td><td>'+entry.address+'</td><td>'+entry.firm_group+'</td><td>Импортровано: <nobr>'+addTime+'</nobr></td></tr>');
      } else if (oldExist) {
        $('#searchDialog-'+type).find('tbody').append('<tr class="notr warning"><td class="text-center"><input type="checkbox" name="'+entry.id+'" value="'+entry.hash+'" onclick=changeMark("'+entry.id+'") id="company'+cId+'"></td><td>'+entry.name+'</td><td>'+entry.address+'</td><td>'+entry.firm_group+'</td><td><a href="#." class="btn btn-xs btn-warning" data-toggle="tooltip" title="Испортировано: '+addTime+'" onclick=importCompany("'+entry.id+'","'+entry.hash+'",true)>Импортировать</a></td></tr>');
      } else {
        totalChecked++;
        $('#searchDialog-'+type).find('tbody').append('<tr class="notr"><td class="text-center"><input type="checkbox" name="'+entry.id+'" value="'+entry.hash+'" onclick=changeMark("'+entry.id+'") id="company'+cId+'" checked></td><td>'+entry.name+'</td><td>'+entry.address+'</td><td>'+entry.firm_group+'</td><td><a href="#." class="btn btn-xs btn-default" onclick=importCompany("'+entry.id+'","'+entry.hash+'")>Импортировать</a></td></tr>');
      }
    });
  }, 'json').done(function() {
    var batchImport = $('#searchDialog-'+type).find('#ok');
    if (totalChecked > 0) {
      batchImport.removeAttr('disabled').html('Импортировать отмеченные <notr>('+totalChecked+')</notr>');
    } else {
      batchImport.attr('disabled', 'disabled').html('Импортировать отмеченные');
    }

    if (callback && typeof(callback) === 'function') {
      callback();
    }
  });
}

/* Функция импорта компании.
========================================================================================= */
function importCompany(id, hash, from2gis, callback) {
  from2gis = typeof from2gis !== 'underfined' ? from2gis : false;
  var importData = {
    importAPI: getParamByName('k'),
    importDomain: 'www.lead4crm.ru',
    importCompanyID: id,
    importCompanyHash: hash,
    getFrom2GIS: from2gis
  };
  var d = new Date(),
      d = d.getFullYear()+'-'+('0'+(d.getMonth()+1)).slice(-2)+'-'+('0'+d.getDate()).slice(-2),
      type = localStorage.getItem('search_id');
  $('#searchDialog-'+type).find('input[name="'+id+'"]').parent().parent().find('td:eq(4) a').html('<i class="fa fa-fw fa-spinner fa-pulse"></i> Загрузка&hellip;');
  $.post('/importCompany/', importData, function (data) {
    if (data.error > 0)
      alert(data.message);
  }, 'json').done(function() {
    $('#searchDialog-'+type).find('input[name="'+id+'"]').attr('disabled', 'disabled').prop('checked', 0).parent().parent().removeClass().addClass('success').find('td:eq(4)').html('Импортировано: <nobr>'+d+'</nobr>');
    if (callback && typeof(callback) === 'function') {
      callback();
    }
  });
}

/* Функция информацию об общем кол-ве импортируемых компаний.
========================================================================================= */
function changeMark(id) {
  var status = $('input[name="'+id+'"]:checked').length;
  var type = localStorage.getItem('search_id');
  var batchImport = $('#searchDialog-'+type).find('#ok');

  if (status) {
    totalChecked++;
  } else {
    totalChecked--;
  }

  if (totalChecked > 0) {
    batchImport.removeAttr('disabled').html('Импортировать отмеченные <notr>('+totalChecked+')</notr>');
  } else {
    batchImport.attr('disabled', 'disabled').html('Импортировать отмеченные');
  }
}

/* Функция выбора полного текста указанного элемента.
========================================================================================= */
function selectText(element) {
    var doc = document;
    var text = doc.getElementById(element);

    if (doc.body.createTextRange) { // ms
        var range = doc.body.createTextRange();
        range.moveToElementText(text);
        range.select();
    } else if (window.getSelection) { // moz, opera, webkit
        var selection = window.getSelection();            
        var range = doc.createRange();
        range.selectNodeContents(text);
        selection.removeAllRanges();
        selection.addRange(range);
    }
}

/* Функция отправляет на указанный 'uin' код верификации.
========================================================================================= */
function sendCode(uin) {
  if (!$('#resendICQCode').prop('disabled')) {
    $('#resendICQCode').attr('disabled', 'disabled');
  }
  $.post('/icq/sendCode/', { uin: uin }, function (data) {
    setTimeout(function() {
      $('#resendICQCode').removeAttr('disabled');
    }, 60000);
  });
}

/* Функция отправляет на указанный 'phone' (телефон) код верификации.
========================================================================================= */
function sendSMSCode(phone, callback) {
  if (!$('#resendSMSCode').prop('disabled')) {
    $('#resendSMSCode').attr('disabled', 'disabled');
  }
  var id;
  $.post('/sms/sendCode/', { phone: phone }, function (data) {
    if (data.response.code == '100') {
      id = data.response.ids[0];
      var isSMSDone = setInterval(function() {
        $.post('/sms/getStatus/', { phone: id }, function (status) {
          if (status.response.code > 102 || status.response.code == '') {
            clearInterval(isSMSDone);
            $('#resendSMSCode').removeAttr('disabled');
            if (status.response.code == 103) {
              var cuBalance = $('#balans').text().replace(/\s/g, '') - status.response.cost.toFixed(2);
              $('#balans').number(cuBalance, 2, '.', ' ');
            }
          }
        });
      }, 2000);
    } else {
      $('#resendSMSCode').removeAttr('disabled');
    }
  }, 'json').done(function() {
    if (callback && typeof(callback) === 'function') {
      callback();
    }
  });
}

function getSMSInfo(phone, callback) {
  var result = false;
  $.post('/sms/getInfo/', { phone: phone }, function (data) {
    cost = data.agregator.cost;
    if (data.agregator.limit.limit == data.agregator.limit.current || 
        data.agregator.balance.balance <= 0
    ) {
      $('#formSMSSetting fieldset').attr('disabled', 'disabled');
      $('#alertSMSService').removeClass('hide');
    } else if (cost > data.balance) {
      $('#formSMSSetting fieldset').attr('disabled', 'disabled');
      $('#sms_price').number(cost, 2, '.', ' ');
      $('#alertSMSUser').removeClass('hide');
    } else {
      $('#sms_price').number(cost, 2, '.', ' ');
      result = true;
    }
  }, 'json').done(function() {
    if (callback && typeof(callback) === 'function') {
      callback(result, cost);
    }
  });
}

/* Функция отправляет на указанный 'email' определенный 'type' сообщения.
========================================================================================= */
function sendEmail(type, serialized, callback) {
  $.post('/email/'+type+'/', serialized).always(function() {
    if (callback && typeof(callback) === 'function') {
      callback();
    }
  });
}

/* Функция получает данные по реферальной программе и показывает соответствующие формы.
 ========================================================================================= */
function getReferalInfo() {
  var _tab = $('li[role="referal"] a'),
      _tab_text = _tab.text();
  _tab.html(_tab_text + ' <i class="fa fa-fw fa-circle-o-notch fa-spin"></i>');
  $.post('/getReferal/', function (data) {
    var _referalForm = $('#referalForm');
    if (data.id > 0) {
      if (!data.accept) {
        _referalForm.find('fieldset').attr('disabled', 'disabled');
        _referalForm.find('#inputFirmName').val(data.firm);
        _referalForm.find('#inputINN').val(data.inn);
        _referalForm.find('#inputBIK').val(data.bik);
        _referalForm.find('#inputRS').val(data.rs);
        $('#refMessageSuccess').removeClass('hide');
      }
    }
    _tab.html(_tab_text);
  }, 'json');
}

/* Функция проверяет является ли переменная 'n' целочисленной.
========================================================================================= */
function isInt(n) {
  return n % 1 === 0;
}

/* Функция проверяет является ли переменная 'n' телефонным номером.
========================================================================================= */
function isPhone(n) {
  var num = n.replace(/[^0-9]/g, '');
  if (num.match(/^(7|8)/g) && num.length == 11)
    return 1;
  else
    return 0;
}

/* Функция проверяет является ли переменная 'n' адресом электронной почты.
========================================================================================= */
function isEmail(n) {
  var re = /^(([^<>()[\]\.,;:\s@\"]+(\.[^<>()[\]\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\.,;:\s@\"]+\.)+[^<>()[\]\.,;:\s@\"]{2,})$/i;
  return re.test(n);
}

/* Функция получает необходимый параметр 'name' из URI текущего скрипта.
========================================================================================= */
function getParamByName(name) {
  name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
  var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
      results = regex.exec(me_src);
  return results === null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
}
