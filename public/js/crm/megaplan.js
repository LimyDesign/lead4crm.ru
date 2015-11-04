$('#formMegaplanConnect').submit(function(event) {
	event.preventDefault();
	$('#megaplanConnect').modal('hide');

});

$('#megaplanConnect #ok').click(function() {
	$('#formMegaplanConnect').submit();
});