( function ( $, mw ) {
	$( document ).ready( function () {
		var $tooltips = $( 'a.achievtitle' );
		if ( $tooltips.length ) {
			mw.loader.using( 'jquery.tipsy', function () {
				$tooltips.tipsy( { title: function () {
					var n = $( '<div></div>' );
					if ( this.hasAttribute('src') ) {
						n.append($( '<div></div>' ).attr( 'class', 'achievimage' ).append($( '<img/>' ).attr( 'src', this.getAttribute('src') )));
					}
					n.append($( '<div></div>' ).html( this.getAttribute('original-title') ));
					return n.html();
				}, gravity: 'w', html: true } );
			} );
		}

		var $avatar = $( 'img.useravatar' );
		if ( $avatar.length ) {
			mw.loader.using( 'jquery.tipsy', function () {
				$avatar.tipsy( { title: function () {
					return $( '<div></div>' ).append($( '<img/>' ).attr( 'src', this.getAttribute('src').replace('_m.', '_l.') )).html();
				}, gravity: 's', html: true } );
			} );
		}
	} );
} ) ( window.jQuery, mediaWiki );
