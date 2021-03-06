$(document).ready(function() {
	$('#webcall form').submit(function(event) {
		event.preventDefault();
		var _form = $(this);
		var action = $(this).attr('action');
		var formData = $(this).serialize();
		$(this).find('fieldset').attr('disabled', 'disabled');
		$('#webcallmsg').removeClass().addClass('alert alert-info').html('<i class="fa fa-fw fa-spinner fa-pulse"></i> Попытка установить соединение...');
		$.post(action, formData, function(data) {
			if (data.Result)
			{
				$('#webcallmsg').removeClass().addClass('alert alert-success').text(data.gencall);
			}
			else
			{
				$('#webcallmsg').removeClass().addClass('alert alert-danger').text(data.ErrorStr);
			}
		}, 'json').done(function() {
			_form.find('fieldset').removeAttr('disabled');
		});
	});
});