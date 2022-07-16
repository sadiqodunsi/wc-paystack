'use strict';
jQuery( function( $ ) {
	
	// Toggle api key settings.
	$( '#woocommerce_sdq_paystack_testmode' ).on( 'change', function() {
		const test_secret_key = $( '#woocommerce_sdq_paystack_test_secret_key' ).parents( 'tr' ).eq( 0 ),
			test_public_key = $( '#woocommerce_sdq_paystack_test_public_key' ).parents( 'tr' ).eq( 0 ),
			live_secret_key = $( '#woocommerce_sdq_paystack_live_secret_key' ).parents( 'tr' ).eq( 0 ),
			live_public_key = $( '#woocommerce_sdq_paystack_live_public_key' ).parents( 'tr' ).eq( 0 );

		if ( $( this ).is( ':checked' ) ) {
			test_secret_key.show();
			test_public_key.show();
			live_secret_key.hide();
			live_public_key.hide();
		} else {
			test_secret_key.hide();
			test_public_key.hide();
			live_secret_key.show();
			live_public_key.show();
		}
	} );

	$( '#woocommerce_sdq_paystack_testmode' ).change();

});