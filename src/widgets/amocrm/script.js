define(['jquery'], function($){
        var CustomWidget = function () {
        var that = this;
        this.helpers={};

        this.helpers.loading = function() {
            var card_entity = AMOCRM.data.current_entity;
            if (card_entity!='companies' && card_entity!='leads'){
                card_id=AMOCRM.data.current_card.id;
                if (card_id!=undefined){
                    card_id=parseInt(card_id);
                    var e = document.createElement('script');
                    e.src = '//connect.facebook.net/en_US/all.js';
                    e.async = true;
                    document.getElementsByTagName('HEAD')[0].appendChild(e);
                    e.onload = function() {
                        $.get("/private/acceptors/facebook.php?amo_contact="+card_id, function(data){
                            $('.fb-form').append(data);
                        });
                    }
                }
            }
        }

        this.callbacks = {
            init: function(){
                var sys = that.system(),
                    wcode = that.get_settings().widget_code;
                settings = that.get_settings();
                return true;
            },
            destroy: function(){
                $(document).off('render:end' + that.ns).off('change' + that.ns);
            },
            bind_actions: function(){
                switch(that.system().area){
                    case 'ccard':{
                        that.helpers.loading();
                        break;
                    }
                        break;
                }
                $(document).on('render:end' + that.ns, function(){
                });
            },
            onSave: function(data){
                var fields = data.fields || {};
                if (fields.conf==''){
                    conf={};
                    conf.conf='installed';
                    data.fields.conf=conf;
                    $.ajax({
                        type:"POST",
                        dataType:'html',
                        data:'check_auth=Y',
                        url: "/private/acceptors/facebook.php",
                        success: function(data){
                            if (data!='Auth'){
                                location.replace(data);
                            } else {
                                if ($('#widget_active__sw:checked').length==0){
                                    location.replace('#'+settings.widget_code);
                                    location.reload();
                                }
                            }

                        }
                    });
                }
                return true;
            },
            contacts: function(){

            },
            render: function(){
                var card_entity = AMOCRM.data.current_entity;
                if (card_entity!='companies' && card_entity!='leads'){
                    that.render_template({
                        caption:{
                            class_name:'js-fb-caption',
                            html:'<img class="fb_logo" src="'+that.params.path+'/images/caption.png" alt="" />'
                        },
                        body:'',
                        render :  '\
                    <div class="fb-form">\
                    <div id="fb_loader"></div>\
                        <div class="fb-form-body">\
                        </div>\
                    </div>\
                    <link type="text/css" rel="stylesheet" href="'+that.params.path+'/main.css" >'
                    });
                    $(document).find('.card-widgets__widget[data-code=' + that.get_settings().widget_code + '] .card-widgets__widget__body').css('padding','0px');
                    $(document).trigger('render:end' +  that.ns);
                }
                return true;
            },
            settings: function(){
                settings = that.get_settings();
                if (settings.conf==undefined){
                    $('.js-widget-save').trigger('button:save:enable');
                }
                if ($('#widget_active__sw:checked').val()=='Y'){
                    $('.widget_settings_block__descr').after('<span id="fb_load" style="margin-left: 47%;" class="spinner-icon"></span>');
                    var wrapper= document.createElement('div');
                    wrapper.id='facebook_settings';
                    $('.widget_settings_block__descr').after(wrapper);
                    $.ajax({
                        type:"GET",
                        dataType:'html',
                        url: "/private/acceptors/facebook.php?auth=Y",
                        success: function(data){
                            $('#fb_load').remove();
                            var wrapper_inner= document.createElement('div');
                            wrapper_inner.id='facebook_settings_inner';
                            wrapper_inner.innerHTML=data;

                            $('#facebook_settings').html(wrapper_inner);
                            $('form#fb_pages').on('submit',function(){
                                if ($(this).hasClass('fb_pages_list_form')){
                                    type=1;
                                    var page_id = $('.page_sel li.control--select--list--item-selected').attr('data-value');
                                    if (page_id == undefined) {
                                        $('#attach').text(that.langs.userLang.No_select).show();
                                        return false;
                                    }
                                    else {
                                        $('#attach').hide();
                                    }
                                } else {
                                    type=2;
                                    page_id=$('form#fb_pages input[name=page_id]').val();
                                }

                                $.post(
                                    '/private/acceptors/facebook.php',
                                    'page_id='+page_id,
                                    function(data) {
                                        if(type==1){
                                            text=that.langs.userLang.Attached;
                                        } else {
                                            text=that.langs.userLang.Unattached;
                                        }

                                        $('#facebook_settings').html('<p style="color: #4F8016;font-size: 20px;">'+text+'</p>');
                                        $('.js-widget-save').trigger('button:save:enable');
                                    },
                                    "html"
                                );
                                return false;
                            });
                            var modal = that.modal || false;
                            if(modal) {
                                modal.$modal.find('.modal-body').trigger('modal:centrify');
                            }
                        }
                    });
                }
            }
        };





        return this;
    };

    return CustomWidget;
});