/**
 * Create an order and initiate payment - Ajax request
 * 
 * @param {int} productID Product ID
 */
function paystackProcessPayment( productID ) {
    if ( ! productID ) {
        alert('Product ID is required.');
        return false;
    }

	jQuery.ajax({
        url : paystack_params.ajax_url,
        type : 'post',
        dataType : "json",
        data : {
		    action    : 'paystack_create_order',
			security  : paystack_params.security,
			product_id : productID
		},
		success : function( response ) {
		    if ( ! response.success ) {
				alert( response.data );
		    } else {
				paystackInitiatePayment( response.data );
		    }
		}
    });
}

/**
 * Verify payment - Ajax request
 * 
 * @param {string} reference Reference from Paystack response
 */
function verifyPaystackTransaction( reference ) {
    jQuery.ajax({
        url : paystack_params.ajax_url,
        type : 'post',
        dataType : "json",
        data : {
		    action    : 'verify_paystack_payment',
			security  : paystack_params.security,
			reference : reference
		},
		success : function( response ) {
		    if ( ! response.success ) {
				alert( response.data );
		    } else {
				alert( response.data );
		    }
		}
	});
}

/**
 * Trigger open Paystack payment popup
 * 
 * @param {object} param Order details
 */
function paystackInitiatePayment(param) {
		
	const PaystackAttr = {
		key: param.public_key,
		email: param.email,
		amount: Number( param.amount ),
		ref: param.txnref,
		currency: param.currency,
		callback: function( response ) {
			verifyPaystackTransaction( response.trxref );
		},
		metadata: {
			custom_fields: paystackCustomFields(param)
		}
	};

	const handler = PaystackPop.setup( PaystackAttr );

	handler.openIframe();
}

/**
 * Paystack custom fields helper function
 * 
 * @param {object} param Order details
 * 
 * @returns {Array} Custom fields array
 */
function paystackCustomFields(param) {

	const custom_fields = [
		{
			"display_name": "Plugin",
			"variable_name": "plugin",
			"value": "sdq_paystack"
		}
	];
	
	const meta_order_id = param.meta_order_id;

	if ( meta_order_id ) {
		custom_fields.push({
			display_name: "Order ID",
			variable_name: "order_id",
			value: meta_order_id
		});
	}
	
	const product = param.product;

	if ( product ) {
		custom_fields.push({
			display_name: "Product",
			variable_name: "product",
			value: product
		});
	}

	return custom_fields;
}