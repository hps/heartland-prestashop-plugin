<div class="securesubmitFormContainer">
	<h3><img alt="Secure Icon" class="secure-icon" src="{$module_dir}img/locked.png" style="background-size:20px 20px;"/>{l s='Pay by Credit Card' mod='securesubmitpayment'}</h3>
	<img alt="Accepted Cards" class="accepted-cards" src="{$module_dir}img/ss-shield@2x.png" />
	<div id="securesubmit-ajax-loader">
		<span>{l s='Your payment is being processed...' mod='securesubmitpayment'}</span>
		<img src="{$module_dir}img/ajax-loader.gif" alt="Loader Icon" />
	</div>
	<form action="{$module_dir}validation.php" method="POST" class="securesubmit-payment-form" id="securesubmit-payment-form"{if isset($securesubmit_credit_card)} style="display: none;"{/if}>
		{if isset($smarty.get.securesubmit_error)}<div class="securesubmit-payment-errors">{$smarty.get.securesubmit_error|base64_decode|escape:html:'UTF-8'}</div>{/if}<a name="securesubmit_error" style="display:none"></a>
		<label>{l s='Card Number*' mod='securesubmitpayment'}</label><br />
		<input type="text" size="20" autocomplete="off" class="securesubmit-card-number" placeholder="CREDIT CARD NUMBER">

		</input>
		<div>
			<div class="block-left">
				<label>{l s='Expiration (MM/YYYY*)' mod='securesubmitpayment'}</label><br />
				<input type="text" size="4" autocomplete="off" class="securesubmit-card-expiry-year" placeholder="MM / YYYY"/>
	        </div>
	        <div>
				<label>{l s='CVC*' mod='securesubmitpayment'}</label><br />
				<input type="text" size="4" autocomplete="off" class="securesubmit-card-cvc" placeholder="CVC"/>
			</div>
        </div>
		<br />
		<button type="submit" class="securesubmit-submit-button">{l s='Submit Payment' mod='securesubmitpayment'}</button>
	</form>
</div>
