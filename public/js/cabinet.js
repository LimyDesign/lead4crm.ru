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

Number.prototype.formatMoney = function(c, d, t) {
  var n = this,
      c = isNaN(c = Math.abs(c)) ? 2 : c,
      d = d == undefined ? '.' : d,
      t = t == undefined ? ' ' : t,
      s = n < 0 ? '-' : '',
      i = parseInt(n = Math.abs(+n || 0).toFixed(c)) + '',
      j = (j = i.length) > 3 ? j  % 3 : 0;
  return s + (j ? i.substr(0, j) + t : '') + i.substr(j).replace(/(\d{3})(?=\d)/g, '$1' + t) + (c ? d + Math.abs(n - i).toFixed(c).slice(2) : '');
};

Date.createFromString = function(string) {
  'use strict';
  var pattern = /^(\d\d\d\d)-(\d\d)-(\d\d) (\d\d):(\d\d):(\d\d)/,
      matches = pattern.exec(string);
  if (!matches) {
    throw new Error("Неправильная строка: " + string);
  }
  var year = matches[1],
      month = matches[2] - 1,
      day = matches[3],
      hour = matches[4],
      minute = matches[5],
      second = matches[6];

  var absoluteMs = Date.UTC(year, month, day, hour, minute, second);
  return new Date(absoluteMs);
};

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
  getUserData();

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

  /* Выделяем ключ доступа при клике по полю с ключом.
   ========================================================================================= */
  $('body').on('mouseup', '.apikey', function() {
    selectText('apikey');
  });

  /* Выделяем реферальную ссылку при клике по полю со ссылкой.
   ========================================================================================= */
  $('body').on('mouseup', '.refurl', function() {
    selectText('refurl');
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
    if ($('#inputSum').val() >= 100) {
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
      var $qty = $('#qty');
      var $balance = $('#balance');
      var $tariff = $('#tariff');
      var $modal = $('#tariffModal');
      var $qty_last = $.number($qty.text(), 0, '', '');
      var $balance_last = $.number($balance.text(), 2, '.', '');
      $modal.modal('show');
      $modal.find('#ok').on('click', function() {
        var b = $(this);
        var t = b.text();
        b.addClass('disabled');
        b.html('<i class="fa fa-fw fa-spinner fa-pulse"></i> ' + t);
        $.post(a,s,function(data) {
          $qty.number(data.qty, 0, '.', ' ');
          $balance.number(data.balance, 2, '.', ' ');
          $tariff.text(data.tariff);
        }, 'json').done(function() {
          $modal.modal('hide');
          b.removeClass('disabled');
          b.text(t);
          $qty.each(function () {
            $(this).prop('Counter', $qty_last).animate({
              Counter: $.number($(this).text(), 0, '', '')
            }, {
              duration: 4000,
              easing: 'swing',
              step: function (now) {
                $(this).number(Math.ceil(now), 0, '', ' ');
              }
            });
          });
          $balance.each(function () {
            $(this).prop('Counter', $balance_last).animate({
              Counter: $.number($(this).text(), 2, '.', '')
            }, {
              duration: 4000,
              easing: 'swing',
              step: function (now) {
                $(this).number(now, 2, '.', ' ');
              }
            });
          });
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

  $('#referalContract').submit(function(e) {
    e.preventDefault();
    var _action = $(this).attr('action'),
        _step01 = $('#refStep01'),
        _step02 = $('#refStep02'),
        _finish = $('#refFinish');
    $.post(_action).done(function() {
      $.growl.notice({ title: "Поздравляем!", message: "Теперь вы можете приступить к заработку." });
      _step01.addClass('hide');
      _step02.addClass('hide');
      _finish.removeClass('hide');
    })
  });

  $('#refURLTable').on('submit', 'form', function(e) {
    e.preventDefault();
    var _form = $(this),
        _input = _form.find('input[name=refurl]').val(),
        _hidden = _form.find('input[name=oldrefurl]').val(),
        _postURL = '/refAddURL/',
        _index = 0;
    _form.find('fieldset').prop('disabled', true);
    if (_hidden) {
      if (_input == _hidden) {
        var data = {id : _form.parent().parent().data('id')};
        refToggleEdit(data, _form, _input);
        return;
      }
      _postURL = '/refUpdateURL/';
      _index = _form.parent().parent().data('id');
    }
    var _posting = $.post(_postURL, { url: _input, id: _index }, 'json');
    _posting.done(function(data) {
      refToggleEdit(data, _form, _input);
    });
  });

  $('#withdrawalsBalance').submit(function(e) {
    e.preventDefault();
    var _this = $(this),
        _action = _this.attr('action'),
        _sum = _this.find('#withdrawalsSum'),
        _sumval = _sum.val();
    if (_sumval >= 1) {
      $.post(_action, { sum: _sumval }, function(data) {
        if ('error' in data) {
          $.growl.error({ title: "Опаньки!", message: data.error });
        } else {
          getUserData();
          refFinRender();
          $.growl.notice({ title: "Все готово!", message: "Заказанная сумма зачислена на лицевой счет, пользуйтесь!" });
        }
      }, 'json');
    } else {
      _sum.focus();
      $.growl.error({ title: 'Упс!', message: 'Сумма должна быть больше или равна 1 рублю.' });
    }
  });

  $('#withdrawalsBank').submit(function(e) {
    e.preventDefault();
    var _this = $(this),
        _action = _this.attr('action'),
        _sum = _this.find('#withdrawalsSumBank'),
        _sumval = _sum.val();
    if (_sumval >= 10000) {
      $.post(_action, { sum: _sumval }, function(data) {
        if ('error' in data) {
          $.growl.error({ title: "Опаньки!", message: data.error });
        } else {
          getUserData();
          refFinRender();
          $.growl.notice({ title: "Все готово!", message: "Заказанная сумма поставлена в очередь на выплату!" });
        }
      }, 'json');
    } else {
      _sum.focus();
      $.growl.error({ title: 'Упс!', message: 'Сумма должна быть больше или равна 10 000 рублей.' });
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

/**
 * Функция получает данные по реферальной програме и показывает соответствующие формы.
 */
function getReferalInfo() {
  var _tab = $('li[role="referal"] a'),
      _tab_text = _tab.text();
  _tab.html(_tab_text + ' <i class="fa fa-fw fa-circle-o-notch fa-spin"></i>');
  $.post('/getReferal/', function (data) {
    var _referalForm = $('#referalForm'),
        _referalContract = $('#referalContract'),
        _alertMsg = $('#refMessageSuccess'),
        _step01 = $('#refStep01'),
        _step02 = $('#refStep02'),
        _finish = $('#refFinish');
    if (data) {
      if (data.id > 0) {
        if (!data.accept) {
          _referalForm.find('fieldset').attr('disabled', 'disabled');
          _referalForm.find('#inputFirmName').val(data.firm);
          _referalForm.find('#inputINN').val(data.inn);
          _referalForm.find('#inputBIK').val(data.bik);
          _referalForm.find('#inputRS').val(data.rs);
          _alertMsg.removeClass('hide');
        } else {
          _step01.addClass('hide');
          if (!data.contract) {
            _step02.removeClass('hide');
            _referalContract.find('#firmName').val(data.firm);
            _referalContract.find('#firmURAddr').val(data.ur_addr);
            _referalContract.find('#firmPOAddr').val(data.po_addr);
            _referalContract.find('#firmINN').val(data.inn);
            _referalContract.find('#firmKPP').val(data.kpp);
            _referalContract.find('#firmOGRN').val(data.ogrn);
            _referalContract.find('#firmOKPO').val(data.okpo);
            _referalContract.find('#firmBank').val(data.bank);
            _referalContract.find('#firmRS').val(data.rs);
            _referalContract.find('#firmKS').val(data.ks);
            _referalContract.find('#firmBIK').val(data.bik);
          } else {
            _step02.addClass('hide');
            _finish.removeClass('hide');
            selectText('refurl');
            refRefreshTable();
            goto(1,'refUsers',false);
            refFinRender();
          }
        }
      }
    } else {
      _referalForm.find('fieldset').removeAttr('disabled');
      _referalForm.find('#inputFirmName').val('');
      _referalForm.find('#inputINN').val('');
      _referalForm.find('#inputBIK').val('');
      _referalForm.find('#inputRS').val('');
      _alertMsg.addClass('hide');
    }
    _tab.html(_tab_text);
  }, 'json');
}

/**
 * Функция формирует финансовую статистику реферера.
 */
function refFinRender() {
  $.post('/getFinReferals/', function(data) {
    var _tableFincance = $('#tableFinance tbody'), _row = '';
    _tableFincance.empty();
    if (data.debet.debet > 0) {
      var _debet = 0, _credit = 0, _subtotal = 0,
          _monthRu = 'января,февраля,марта,апреля,мая,июня,июля,августа,сентября,октября,ноября,декабря'.split(',');
      if (data.credit.length > 0) {
        data.credit.forEach(function(entry, index) {
          var _dateParse = Date.createFromString(entry.paydate),
              _dateDB = _dateParse.getDate() + ' ' + _monthRu[_dateParse.getMonth()] + ' ' + _dateParse.getFullYear() + ' г.';
          _credit = parseInt(entry.credit).formatMoney(2);
          _subtotal = _subtotal == 0 ? parseInt(data.debet.debet) : _subtotal;
          _debet = _debet == 0 ? _subtotal : _subtotal = _subtotal - parseInt(data.credit[index-1].credit);
          _row = '<tr><td>'+_dateDB+'</td><td>'+_credit+'&nbsp;<i class="fa fa-rub"></i></td><td>'+_debet.formatMoney(2)+'&nbsp;<i class="fa fa-rub"></i></td>' + _row;
        });
        _debet = _subtotal - parseInt(data.credit[data.credit.length - 1].credit);
      } else {
        _debet = parseInt(data.debet.debet);
      }
      _row = '<tr class="active"><td>Сегодня</td><td>0&nbsp;<i class="fa fa-rub"></i></td><td><strong>'+_debet.formatMoney(2)+'</strong>&nbsp;<i class="fa fa-rub"></i></td>' + _row;
      _tableFincance.append(_row);
    } else {
      _row = '<tr><td colspan="3" class="text-center"><strong>Ой-ё-ё!</strong><br>Так вышло, что у вас еще не накоплено ни одного рубля.<br>Привлекайте пользователей и получайте отчисления!</td>';
      _tableFincance.append(_row);
    }
  });
}

/**
 * Фунция выводит таблицу рефералов, привлеченных данным реферером.
 *
 * @param page Номер страницы.
 * @param tab Область работы функции.
 * @param scroll Указывает на необходимость прокрутки до начала таблицы пользователей.
 */
function goto(page, tab, scroll) {
  scroll = scroll == undefined ? true : scroll;
  if (tab == 'refUsers') {
    $.post('/getAllReferals/', { page: page }, function(data) {
      var _tableReferals = $('#tableReferals tbody'),
          _pagination = $('#refFinish ul.pagination'),
          _row, _vk, _ok, _fb, _gp, _mr, _ya;
      _tableReferals.empty();
      if (data.length > 0) {
        var uncheck = '<i class="fa fa-square-o"></i>',
            check = '<i class="fa fa-check-square-o"></i>',
            _pagi = '';
        data.forEach(function(entry, index) {
          var num = (50 * page - 50) + 1 + index, _total = 0;
          _vk = _ok = _fb = _gp = _mr = _ya = uncheck;
          if (entry.email == null) entry.email = '&lt;адрес не указан&gt;';
          else entry.email = '<a href="mailto:'+entry.email+'">'+entry.email+'</a>';
          if (entry.vk) _vk = check;
          if (entry.ok) _ok = check;
          if (entry.fb) _fb = check;
          if (entry.gp) _gp = check;
          if (entry.mr) _mr = check;
          if (entry.ya) _ya = check;
          if (entry.company == null) entry.company = '';
          if (entry.total != null) _total = parseInt(entry.total);
          _row = '<tr><td>'+num+'</td><td>'+entry.email+'</td><td>'+_vk+'</td><td>'+_ok+'</td><td>'+_fb+'</td><td>'+_gp+'</td><td>'+_mr+'</td><td>'+_ya+'</td><td>'+entry.company+'</td><td>'+_total.formatMoney(2)+'&nbsp;<i class="fa fa-rub"></i></td></tr>';
          _tableReferals.append(_row);
        });
        var total_users = parseInt(data[0].total_users);
        if (total_users > 50) {
          var _page = 1;
          for (var i = 0; i < total_users; i += 50) {
            if (_page == page)
              _pagi += '<li class="active"><span>'+_page+'</span></li>';
            else
              _pagi += '<li><a href="javascript:goto('+_page+',\''+tab+'\');">'+_page+'</a></li>';
            _page++;
          }
        }
        _pagination.empty().html(_pagi);
      } else {
        _row = '<tr><td colspan="10" class="text-center"><strong>Упс!</strong><br>Вы еще не привлекли ни одного пользователя.<br>Возпользуйтесь реферальной ссылкой.</td></tr>';
        _tableReferals.append(_row);
        _pagination.empty();
      }
    }).done(function() {
      if (scroll)
        scrollTo('#tableReferals');
    });
  }
}

function refContractAccept() {
  var _referalContract = $('#referalContract'),
      _status = _referalContract.find('#firmAccept').is(':checked'),
      _button = _referalContract.find('button');
  if (_status)
      _button.removeAttr('disabled');
  else
      _button.attr('disabled', 'disabled');
}

function refAddNewURL() {
  var _refURLTabel = $('#refURLTable'),
      _rowLastId = _refURLTabel.find('tbody tr:last').data("id") || 0,
      _rowCount = _rowLastId + 1,
      _newInput = '<form><fieldset><div class="input-group input-group-sm"><input type="text" name="refurl" class="form-control"><div class="input-group-btn"><button class="btn btn-success" type="submit"><i class="fa fa-check"></i></button><button class="btn btn-danger" type="button" onclick="refDeleteRow('+_rowCount+');"><i class="fa fa-trash"></i></button></div></div><input type="hidden" name="oldrefurl" value=""></fieldset></form>',
      _newRow = '<tr data-id="'+_rowCount+'"><td>'+_newInput+'</td><td>&mdash;</td><td>&mdash;</td>';
  _refURLTabel.find('tbody').append(_newRow);
}

function refEditRow(id) {
  var _refURLTabel = $('#refURLTable tbody'),
      _val = _refURLTabel.find('tr[data-id='+id+'] td:first').text();
      _input = '<form><fieldset><div class="input-group input-group-sm"><input type="text" name="refurl" value="'+_val+'" class="form-control"><div class="input-group-btn"><button class="btn btn-success" type="submit"><i class="fa fa-check"></i></button><button class="btn btn-danger" type="button" onclick="refDeleteRow('+id+');"><i class="fa fa-trash"></i></button></div></div><input type="hidden" name="oldrefurl" value="'+_val+'"></fieldset></form>';
  _refURLTabel.find('tr[data-id='+id+'] td:first').empty().html(_input);
}

function refToggleEdit(data, form, input) {
  var _html = '<a href="javascript:refEditRow('+data.id+');" class="jslink">'+input+'</a>';
  form.parent().parent().data('id', data.id);
  form.after(_html).remove();
}

function refDeleteRow(id) {
  var _refURLTabel = $('#refURLTable'),
      _row = _refURLTabel.find('tbody tr[data-id='+id+']'),
      _hidden = _row.find('input[name=oldrefurl]').val();
  if (_hidden) {
    $.post('/refDeleteURL/', { id: id });
  }
  _row.remove();
}

function refRefreshTable() {
  $.post('/refGetURL/', function(data) {
    var _table = $('#refURLTable tbody'), _html, _url, _confirm, _moderate;
    if (data.length > 0) {
      _table.empty();
      data.forEach(function(entry) {
        if (entry.confirm) _confirm = '+';
        else _confirm = '&mdash;';

        if (entry.moderate) _moderate = '+';
        else _moderate = '&mdash;';

        _url = '<a href="javascript:refEditRow('+entry.id+');" class="jslink">'+entry.url+'</a>';
        _html = '<tr data-id="'+entry.id+'"><td>'+_url+'</td><td>'+_confirm+'</td><td>'+_moderate+'</td></tr>';
        _table.append(_html);
      });
    }
  }, 'json');
}

/**
 * Функция обновляет данные о пользователе:
 * - общее кол-во доступных карточек компаний
 * - баланс пользователя
 * - тарифный план
 */
function getUserData() {
  $.post('/getUserData/', function(data) {
    $('#balance').number(data.balance, 2, '.', ' ');
    $('#tariff').text(data.tariff);
    $('#qty').number(data.qty, 0, '.', ' ');
    qty = data.qty;
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
