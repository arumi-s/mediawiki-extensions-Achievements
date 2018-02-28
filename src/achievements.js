( function ( $, mw ) {
	$( document ).ready( function () {
		var $tooltips = $( 'a.achievtitle' );
		if ( $tooltips.length ) {
			mw.loader.using( 'jquery.tipsy', function () {
				$tooltips.tipsy( { gravity: 'w' } );
			} );
		}
	} );
} ) ( window.jQuery, mediaWiki );
