jQuery(document).ready(function($) {
	
	//console.log($('#featured-embed-url'));
	$('#featured-embed-url').on('focus', function(event) {
		
		var field = $(this);
		if(field.val() != '') {
			$('#featured-embed-form .spinner').show();
		} else {
			$('#featured-embed-form .spinner').hide();
		}
		
	});
	
	$('#featured-embed-remove').on('click', function(event) {
		
		event.preventDefault();
		
		// Data that is passed via ajax
		var data = {
			action: 'delete_embed_meta',
			post_id: $('#post_id').val(),
			nonce: $('#featured_embed_nonce').val(),
		};

		// send the request
		$.post(ajaxurl, data, function(response) {
			$('#featured-embed-preview').addClass('hidden');
			$('#featured-embed-form').removeClass('hidden');
			$('#featured-embed-url').val('');
		});
	});
	
});