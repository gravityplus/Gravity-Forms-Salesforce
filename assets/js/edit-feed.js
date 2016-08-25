/**
 * Custom js script to load at edit feed screen
 *
 * @copyright Copyright 2013, Katz Web Services, Inc.
 *
 * @since 2.4.2
 */

(function( $ ) {

	$(document).ready( function() {

		// on changing form, change field options for Primary Field setting
		$('#gf_salesforce_list').change( function() {

			var data = {
				action: 'get_options_as_fields',
				sf_object: $(this).val(),
				nonce: ajax_object.nonce,
			}

			$.post( ajax_object.ajaxurl, data, function( response ) {
				if( response ) {
					$('#salesforce_primary_field').empty().append( response );
				}
			});

		});

		$('.sort tbody').sortable({
			axis: 'y',
			update: function(event, ui){
				var data = {
					'action': 'rg_update_feed_sort',
					'sort': [],
					'nonce': ajax_object.nonce,
				};

				$(this).children().each(function(index, element) {
					var id = $(element).attr('data-id')
					data.sort.push(id);
				})

				$.post( ajax_object.ajaxurl, data );

			}
		}).disableSelection();

	});

}(jQuery));
