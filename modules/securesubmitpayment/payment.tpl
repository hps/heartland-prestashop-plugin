<div class="securesubmitFormContainer">
	<h3><img alt="Secure Icon" class="secure-icon" src="{$module_dir}img/secure-icon.png" />{l s='Pay by Credit Card' mod='securesubmitpayment'}</h3>
	<div id="securesubmit-ajax-loader"> 
		<span>{l s='Your payment is being processed...' mod='securesubmitpayment'}</span>
		<img src="{$module_dir}img/ajax-loader.gif" alt="Loader Icon" />
	</div>
	<form action="{$module_dir}validation.php" method="POST" class="securesubmit-payment-form" id="securesubmit-payment-form"{if isset($securesubmit_credit_card)} style="display: none;"{/if}>
		{if isset($smarty.get.securesubmit_error)}<div class="securesubmit-payment-errors">{$smarty.get.securesubmit_error|base64_decode|escape:html:'UTF-8'}</div>{/if}<a name="securesubmit_error" style="display:none"></a>
		<label>{l s='Card Number' mod='securesubmitpayment'}</label><br />
		<input type="text" size="20" autocomplete="off" class="securesubmit-card-number" />
		<div>
			<div class="block-left">
				<div class="clear"></div>
				<label>{l s='Expiration (MM/YYYY)' mod='securesubmitpayment'}</label><br />
				<select id="month" name="month" class="securesubmit-card-expiry-month">
					<option value="01">{l s='January' mod='securesubmitpayment'}</option>
					<option value="02">{l s='February' mod='securesubmitpayment'}</option>
					<option value="03">{l s='March' mod='securesubmitpayment'}</option>
					<option value="04">{l s='April' mod='securesubmitpayment'}</option>
					<option value="05">{l s='May' mod='securesubmitpayment'}</option>
					<option value="06">{l s='June' mod='securesubmitpayment'}</option>
					<option value="07">{l s='July' mod='securesubmitpayment'}</option>
					<option value="08">{l s='August' mod='securesubmitpayment'}</option>
					<option value="09">{l s='September' mod='securesubmitpayment'}</option>
					<option value="10">{l s='October' mod='securesubmitpayment'}</option>
					<option value="11">{l s='November' mod='securesubmitpayment'}</option>
					<option value="12">{l s='December' mod='securesubmitpayment'}</option>
				</select>
				<span> / </span>
				<select id="year" name="year" class="securesubmit-card-expiry-year">
					<option value="2014">2014</option>
					<option value="2015">2015</option>
					<option value="2016">2016</option>
					<option value="2017">2017</option>
					<option value="2018">2018</option>
					<option value="2019">2019</option>
					<option value="2020">2020</option>
					<option value="2021">2021</option>
					<option value="2022">2022</option>
					<option value="2023">2023</option>
					<option value="2024">2024</option>
					<option value="2025">2025</option>
					<option value="2026">2026</option>
					<option value="2027">2027</option>
					<option value="2028">2028</option>
					<option value="2029">2029</option>
					<option value="2030">2030</option>
		        </select>
	        </div>
	        <div>
				<label>{l s='CVC' mod='securesubmitpayment'}</label><br />
				<input type="text" size="4" autocomplete="off" class="securesubmit-card-cvc" />
				<a href="javascript:void(0)" class="securesubmit-card-cvc-info" style="border: none;">
					{l s='What\'s this?' mod='securesubmitpayment'}
					<div class="cvc-info">
					{l s='The CVC (Card Validation Code) is a 3 or 4 digit code on the reverse side of Visa, MasterCard and Discover cards and on the front of American Express cards.' mod='securesubmitpayment'}
					</div>
				</a>
			</div>
        </div>
		<br />
		<button type="submit" class="securesubmit-submit-button">{l s='Submit Payment' mod='securesubmitpayment'}</button>
	</form>
</div>
