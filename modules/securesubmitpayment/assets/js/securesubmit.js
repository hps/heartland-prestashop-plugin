(function ($) {
  $(document).ready(function () {
    secureSubmitPrepareFields();
  });

  function secureSubmitResponseHandler(response) {
    var $form = $("form.securesubmit-payment-form");
    if (response.message) {
      $('.securesubmit-payment-form')
        .before('<ul class="error-message alert alert-danger"><li>' + response.message + '</li></ul>');
        var submit_button = document.getElementById('submit_button');
        submit_button.classList.remove("disable-button");
    } else {
      //alert ('[' + response.token_value + ']');
      $form.append("<input type='hidden' class='securesubmitToken' name='securesubmitToken' value='" + response.paymentReference + "'/>");
      $form.submit();
    }
  }

  function secureSubmitPrepareFields() {
    var securesubmit_public_key_test = securesubmit_public_key;
    GlobalPayments.configure({
      "publicApiKey": securesubmit_public_key_test
    });

    // Create a new `HPS` object with the necessary configuration
    GP = GlobalPayments.ui.form({
      fields: {
        "card-number": {
          placeholder: "•••• •••• •••• ••••",
          target: "#securesubmitIframeCardNumber"
        },
        "card-expiration": {
          placeholder: "MM / YYYY",
          target: "#securesubmitIframeCardExpiration"
        },
        "card-cvv": {
          placeholder: "•••",
          target: "#securesubmitIframeCardCvv"
        },
        "submit": {
          target: "#submit_button",
          text: "Submit Payment"
        }
      },
      styles:  {
        '#secure-payment-field' : {
          'background-color' : '#fff',
          'border'           : '1px solid #ccc',
          'border-radius'    : '4px',
          'display'          : 'block',
          'font-size'        : '14px',
          'height'           : '35px',
          'padding'          : '6px 12px',
          'width'            : '100%',
        },
        '#secure-payment-field:focus' : {
          "border": "1px solid lightblue",
          "box-shadow": "0 1px 3px 0 #cecece",
          "outline": "none"
        },
        'button#secure-payment-field.submit' : {
              'width': 'unset',
              'flex': 'unset',
              'float': 'right',
              'color': '#fff',
              'background': '#2e6da4',
              'cursor': 'pointer'
        },
        'input[placeholder]' : {
          'letter-spacing' : '.5px',
        },
      }
    });

    GP.on('submit', 'click', function() {
      var submit_button = document.getElementById('submit_button');
      submit_button.classList.add("disable-button");
    });

    GP.on("token-success", function(resp) {
      GP.errors();
      if(resp.details.cardSecurityCode == false) {
        $('.securesubmit-payment-form').before('<ul class="error-message alert alert-danger"><li>Invalid Card Details</li></ul>');
        var submit_button = document.getElementById('submit_button');
        submit_button.classList.remove("disable-button");
      } else {
        secureSubmitResponseHandler(resp);
      }
    });

    GP.on("token-error", function(resp) {
      GP.errors();
      if(resp.error) {
        resp.reasons.forEach(function(reason) {
          secureSubmitResponseHandler(reason);
        })
      }
      var submit_button = document.getElementById('submit_button');
      submit_button.classList.remove("disable-button");
    });

    GP.errors = function() {
      $(".error-message").remove();
    }
  }

}(jQuery));
