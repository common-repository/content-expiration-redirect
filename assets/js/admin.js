jQuery(document).ready( function($) {
	$('.edit-expiringdate').click(function () {
		if ($('#expiringdatediv').is(':hidden')) {
			$('#expiringdatediv').slideDown('normal');
			$('.edit-expiringdate').hide();
		}
		return false;
	});
	$('.set-expiringdate').click(function() {
		$('#expiringdatediv').slideUp('normal');
		$('.edit-expiringdate').show();
		var edate = $('.expiring-datepicker').val();
		$('.setexpiringdate').html( edate );
		return false;
	});
	$('.cancel-expiringdate').click(function() {
		$('#expiringdatediv').slideUp('normal');
		$('.edit-expiringdate').show();
		var edate = $('.expiring-datepicker').attr('data-exdate');
		$('.setexpiringdate').html( edate );
		$('.expiring-datepicker').val( edate );
		return false;
	});
})
