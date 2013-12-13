jQuery(document).ready(function($) {
	
	$('#featured-embed-remove').on('click', function(event) {
		
		event.preventDefault();
		
		// Data that is passed via ajax
		var data = {
			action: 'delete_embed_data',
			post_id: $('#post_id').val(),
			nonce: $('#featured_embed_nonce').val(),
			url: '',
			oembed: ''
		};

		// send the request
		$.post(ajaxurl, data, function(response) {
			$('#featured-embed-preview').addClass('hidden');
			$('#featured-embed-form').removeClass('hidden');
			$('#featured-embed-url').val('');
		});
	});

});