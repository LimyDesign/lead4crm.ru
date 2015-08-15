$(document).ready(function() {
	$('#webcall form').submit(function(event) {
		event.preventDefault();
		var action = $(this).attr('action');
		var formData = $(this).serialize();
		$.post(action, formData, function(data) {
			$('#webcallmsg').removeClass('hide').html(data);
		});
	});
});