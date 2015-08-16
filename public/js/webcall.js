$(document).ready(function() {
	$('#webcall form').submit(function(event) {
		event.preventDefault();
		var action = $(this).attr('action');
		var formData = $(this).serialize();
		$.post(action, formData, function(data) {
			if (data.Result)
			{
				$('#webcallmsg').removeClass().addClass('alert alert-success').text(data.gencall).delay(5000).hide();
			}
			else
			{
				$('#webcallmsg').removeClass().addClass('alert alert-danger').text(data.ErrorStr).delay(5000).hide();
			}
		}, 'json');
	});
});