{if $securesubmit_order.valid == 1}
	<div class="conf confirmation">{l s='Congratulations, your payment has been approved and your order has been saved under the reference' mod='securesubmitpayment'} <b>{$securesubmit_order.reference|escape:html:'UTF-8'}</b>.</div>
{else}
	<div class="error">{l s='Sorry, unfortunately an error occured during the transaction.' mod='securesubmitpayment'}<br /><br />
	{l s='Please double-check your credit card details and try again or feel free to contact us to resolve this issue.' mod='securesubmitpayment'}<br /><br />
	({l s='Your Order\'s Reference:' mod='securesubmitpayment'} <b>{$securesubmit_order.reference|escape:html:'UTF-8'}</b>)</div>
{/if}
