/*!
 * Add autocomplete suggestions for id of achievements.
 */
( function ( mw, $ ) {
	var api, config;

	config = {
		fetch: function ( userInput, response, maxRows ) {
			var node = this[ 0 ];

			api = api || new mw.Api();

			$.data( node, 'request', api.get( {
				formatversion: 2,
				action: 'query',
				list: 'allachievements',
				aaprefix: userInput,
				aalimit: maxRows,
				aastaged: userInput.indexOf(':') !== -1
			} ).done( function ( data ) {
				var achievements = $.map( data.query.allachievements, function ( achiev ) {
					return achiev.id;
				} );
				response( achievements );
			} ) );
		},
		cancel: function () {
			var node = this[ 0 ],
				request = $.data( node, 'request' );

			if ( request ) {
				request.abort();
				$.removeData( node, 'request' );
			}
		}
	};

	$( function () {
		$( '.mw-autocomplete-achievement' ).suggestions( config );
	} );
}( mediaWiki, jQuery ) );
