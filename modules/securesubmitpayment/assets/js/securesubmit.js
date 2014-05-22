jQuery( function() {	
	jQuery('.securesubmit-submit-button').bind('click', secureSubmitFormHandler);
});
function secureSubmitFormHandler() {
	//alert('0');
	//if ( jQuery('#payment_method_securesubmit').is(':checked') ) {
		if ( jQuery( 'input.securesubmitToken' ).size() == 0 ) {
			var card 	= jQuery('.securesubmit-card-number').val().replace(/\D/g, '');
			var cvc 	= jQuery('.securesubmit-card-cvc').val();
			var month	= jQuery('.securesubmit-card-expiry-month').val();
			var year	= jQuery('.securesubmit-card-expiry-year').val();
			hps.tokenize({
				data: {
					public_key: securesubmit_public_key,
					number: card,
					cvc: cvc,
					exp_month: month,
					exp_year: year
				},
				success: function(response) {
					secureSubmitResponseHandler(response);
				},
				error: function(response) {
					secureSubmitResponseHandler(response);
				}
			});
			return false;
		//}
	}

	return true;
}

function secureSubmitResponseHandler1( response ) {
    if ( response.message ) {
        jQuery('.securesubmit-card-number').closest('p').before( '<ul class="error"><li>' + response.message + '</li></ul>' );
        $form.unblock();
    } else {
    	//alert ('[' + response.token_value + ']');
		jQuery('#securesubmitToken').val(response.token_value);
		jQuery('.securesubmit-submit-button').unbind('click');
		jQuery('.securesubmit-submit-button').click();
    }
}

function secureSubmitResponseHandler( response ) {
    var $form = jQuery("form.securesubmit-payment-form");
    if ( response.message ) {
        jQuery('.securesubmit-card-number').closest('p').before( '<ul class="error"><li>' + response.message + '</li></ul>' );
        $form.unblock();
    } else {
    	//alert ('[' + response.token_value + ']');
        $form.append("<input type='hidden' class='securesubmitToken' name='securesubmitToken' value='" + response.token_value + "'/>");
        $form.submit();
    }
}