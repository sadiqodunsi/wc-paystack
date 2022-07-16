# wc-paystack
This is a [Paystack](https://paystack.com/) plugin for WooCommerce that makes it possible for customers to pay for products without going through the default WooCommerce checkout page.

 - The plugin automatically creates an order with the specified product ID and opens the Paystack payment popup.

 - Once payment is completed, the plugin verifies the payment by making a request to [Paystack verify API](https://paystack.com/docs/payments/verify-payments) from the server.

 - If everything checks out, a success response is returned.

Useful for websites that sells a handful of products and finds the WooCommerce cart -> checkout process cumbersome.

Useful for websites that does not allow multiple products in cart.

Usage:

Initiate a transaction by making a call to the following javascript function with your product ID: `paystackProcessPayment( productID );`

Example:

`<button type="button" data-product="75007" class="paystack-pay">Pay ₦600</button>`

`jQuery('.paystack-pay').on('click', function() {
	const productID = jQuery(this).data("product");
	paystackProcessPayment( productID );
});`
