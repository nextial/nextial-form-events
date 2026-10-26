/* Nextial Form Events admin page: select-on-click fields and copy buttons. */
( function () {
	document.addEventListener( 'click', function ( e ) {
		var el = e.target;
		if ( el.matches && el.matches( 'input.nextial-fe-select' ) ) {
			el.select();
			return;
		}
		var btn = el.closest ? el.closest( 'button[data-nextial-fe-copy]' ) : null;
		if ( ! btn ) {
			return;
		}
		var field = document.getElementById( btn.getAttribute( 'data-nextial-fe-copy' ) );
		if ( field && navigator.clipboard ) {
			navigator.clipboard.writeText( field.value );
			btn.textContent = btn.getAttribute( 'data-nextial-fe-copied' ) || btn.textContent;
		}
	} );
}() );
