(function ($) {
  $(document).ready(function () {
    bindHandler();
  });

  $(document).ajaxComplete(function () {
    setTimeout(bindHandler, 1000);
  });
  
  function bindHandler() {
    $('.securesubmit-submit-button')
      .unbind('click', secureSubmitFormHandler)
      .bind('click', secureSubmitFormHandler);
  }

  function secureSubmitFormHandler() {
    //alert('0');
    //if ($('#payment_method_securesubmit').is(':checked')) {
      if ($('input.securesubmitToken').size() === 0) {
        var card  = $('.securesubmit-card-number').val().replace(/\D/g, '');
        var cvc  = $('.securesubmit-card-cvc').val();
        var month = $('.securesubmit-card-expiry-month').val();
        var year = $('.securesubmit-card-expiry-year').val();
        hps.tokenize({
          data: {
            public_key: securesubmit_public_key,
            number: card,
            cvc: cvc,
            exp_month: month,
            exp_year: year
          },
          success: secureSubmitResponseHandler,
          error: secureSubmitResponseHandler
        });
        return false;
      }
   //}

   return true;
  }

  function secureSubmitResponseHandler(response) {
    var $form = $("form.securesubmit-payment-form");
    if (response.message) {
      $('.securesubmit-card-number')
        .closest('p')
        .before('<ul class="error"><li>' + response.message + '</li></ul>');
      $form.unblock();
    } else {
      //alert ('[' + response.token_value + ']');
      $form.append("<input type='hidden' class='securesubmitToken' name='securesubmitToken' value='" + response.token_value + "'/>");
      $form.submit();
    }
  }
}(jQuery));
