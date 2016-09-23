<?php
if (!defined('_PS_VERSION_'))
	exit;
/**
 * This payment module enables the processing of
 * credit card transactions through the SecureSubmit
 * framework.
 *
 *  @author   Heartland Payment Systems
 *  @version  Release: 1.0.0
 *  @license  See licence.txt
 */ 
class SecureSubmitPayment extends PaymentModule
{
	public $limited_countries = array('us');
	public $limited_currencies = array('USD');
	protected $backward = false;
    const ONE_MINUTE_IN_SECONDS = 60;

	public function __construct()
	{
		$this->name = 'securesubmitpayment';
		$this->tab = 'payments_gateways';
		$this->version = '1.0.0';
		$this->author = 'Heartland Payment Systems';
		$this->need_instance = 0;

		parent::__construct();

		$this->displayName = $this->l('SecureSubmit');
		$this->description = $this->l('Heartland Payment Systems payment gateway connection.');
		$this->confirmUninstall = $this->l('Warning: Are you sure you want to uninstall this module?');

		if (_PS_VERSION_ < '1.5')
		{
			$this->backward_error = $this->l('In order to work properly in PrestaShop v1.4, SecureSubmit module requires the backward compatibility module at least v0.3.').'<br />'.
				$this->l('You can download this module for free here: http://addons.prestashop.com/en/modules-prestashop/6222-backwardcompatibility.html');

                if (file_exists(_PS_MODULE_DIR_.'backwardcompatibility/backward_compatibility/backward.php'))
			{
				include(_PS_MODULE_DIR_.'backwardcompatibility/backward_compatibility/backward.php');
				$this->backward = true;
			} else {
				$this->warning = $this->backward_error;
            }
		} else {
			$this->backward = true;
        }
	}

	/**
	 * SecureSubmit's module installation
	 *
	 * @return boolean Install result
	 */
	public function install()
	{
		if (!$this->backward && _PS_VERSION_ < 1.5)
		{
			echo '<div class="error">'.Tools::safeOutput($this->backward_error).'</div>';
			return false;
		}

		/* For 1.4.3 and less compatibility */
		$updateConfig = array(
			'PS_OS_CHEQUE' => 1,
			'PS_OS_PAYMENT' => 2,
			'PS_OS_PREPARATION' => 3,
			'PS_OS_SHIPPING' => 4,
			'PS_OS_DELIVERED' => 5,
			'PS_OS_CANCELED' => 6,
			'PS_OS_REFUND' => 7,
			'PS_OS_ERROR' => 8,
			'PS_OS_OUTOFSTOCK' => 9,
			'PS_OS_BANKWIRE' => 10,
			'PS_OS_PAYPAL' => 11,
			'PS_OS_WS_PAYMENT' => 12);

		foreach ($updateConfig as $u => $v)
			if (!Configuration::get($u) || (int)Configuration::get($u) < 1)
			{
				if (defined('_'.$u.'_') && (int)constant('_'.$u.'_') > 0)
					Configuration::updateValue($u, constant('_'.$u.'_'));
				else
					Configuration::updateValue($u, $v);
			}

		$ret = parent::install() && 
            $this->registerHook('payment') && 
            $this->registerHook('header') && 
            $this->registerHook('orderConfirmation') &&
            Configuration::updateValue('HPS_MODE', 0) && 
            Configuration::updateValue('HPS_PAYMENT_ORDER_STATUS', (int)Configuration::get('PS_OS_PAYMENT'));

		$this->registerHook('displayMobileHeader');

		return $ret;
	}

	/**
	 * SecureSubmit's module uninstallation (Configuration values, database tables...)
	 *
	 * @return boolean Uninstall result
	 */
	public function uninstall()
	{
		return parent::uninstall() && 
		Configuration::deleteByName('HPS_MODE') && 
		Configuration::deleteByName('HPS_PUBLIC_KEY_TEST') && 
		Configuration::deleteByName('HPS_PUBLIC_KEY_LIVE')&& 
		Configuration::deleteByName('HPS_SECRET_KEY_TEST') && 
		Configuration::deleteByName('HPS_SECRET_KEY_LIVE') &&
		Configuration::deleteByName('HPS_PAYMENT_ORDER_STATUS');
	}

	public function hookDisplayMobileHeader()
	{
		return $this->hookHeader();
	}

	/**
	 * Load Javascripts and CSS related to the SecureSubmit's module
	 * during the checkout process only.
	 *
	 * @return string SecureSubmit's JS dependencies
	 */
	public function hookHeader()
	{
		if (!$this->backward)
			return;

		if (!in_array($this->context->currency->iso_code, $this->limited_currencies))
			return;

		if (Tools::getValue('controller') != 'order-opc' && (!($_SERVER['PHP_SELF'] == __PS_BASE_URI__.'order.php' || $_SERVER['PHP_SELF'] == __PS_BASE_URI__.'order-opc.php' || Tools::getValue('controller') == 'order' || Tools::getValue('controller') == 'orderopc' || Tools::getValue('step') == 3)))
			return;

		$this->context->controller->addCSS($this->_path.'securesubmit.css');
        $this->context->controller->addJS($this->_path.'assets'.DS.'js'.DS.'secure.submit-1.0.2.js');
        $this->context->controller->addJS($this->_path.'assets'.DS.'js'.DS.'securesubmit.js');

		return '<script type="text/javascript">var securesubmit_public_key = \''.addslashes(Configuration::get('HPS_MODE') ? Configuration::get('HPS_PUBLIC_KEY_LIVE') : Configuration::get('HPS_PUBLIC_KEY_TEST')).'\';</script>';
	}

	public function hookPayment($params)
	{
		/* If 1.4 and no backward then leave */
		if (!$this->backward)
			return;

		/* If the currency is not supported, then leave */
		if (!in_array($this->context->currency->iso_code, $this->limited_currencies))
			return ;

		return $this->display(__FILE__, 'payment.tpl');
	}
	
	/**
	 * Display a confirmation message after an order has been placed.
	 *
	 * @param array $params Hook parameters
	 * @return string SecureSubmit's payment confirmation screen
	 */
	public function hookOrderConfirmation($params)
	{
		if (!isset($params['objOrder']) || ($params['objOrder']->module != $this->name))
			return false;
		
		if ($params['objOrder'] && Validate::isLoadedObject($params['objOrder']) && isset($params['objOrder']->valid))
			$this->smarty->assign('securesubmit_order', array('reference' => isset($params['objOrder']->reference) ? $params['objOrder']->reference : '#'.sprintf('%06d', $params['objOrder']->id), 'valid' => $params['objOrder']->valid));

		return $this->display(__FILE__, 'order-confirmation.tpl');
	}

	/**
	 * Process a payment with SecureSubmit.
	 *
	 * @param string $token SecureSubmit card token returned by JS call
	 */
	public function processPayment($token)
	{
		/* If 1.4 and no backward, then leave */
		if (!$this->backward)
			return;

		require_once(dirname(__FILE__).'/lib/Hps.php');
        
        $address = new Address((int)$this->context->cart->id_address_invoice);
        $address_state =  new State($address->id_state);
        $customer = new Customer($this->context->cart->id_customer);
        $amount = $this->context->cart->getOrderTotal();
        
        $config = new HpsServicesConfig();

        $config->secretApiKey = Configuration::get('HPS_MODE') ? Configuration::get('HPS_SECRET_KEY_LIVE') : Configuration::get('HPS_SECRET_KEY_TEST');
        $config->versionNumber = '1540';
        $config->developerId = '002914';

        $chargeService = new HpsCreditService($config);

        $hpsaddress = new HpsAddress();
        $hpsaddress->address = $address->address1;
        $hpsaddress->city = $address->city;
        $hpsaddress->state = $address_state->name;
        $hpsaddress->zip = preg_replace('/[^0-9]/', '', $address->postcode);
        $hpsaddress->country = $address->country;

        $cardHolder = new HpsCardHolder();
        $cardHolder->firstName = $customer->firstname;
        $cardHolder->lastName = $customer->lastname;
        $cardHolder->phone = preg_replace('/[^0-9]/', '', $address->phone);
        $cardHolder->emailAddress = $customer->email;
        $cardHolder->address = $hpsaddress;

        $hpstoken = new HpsTokenData();
        $hpstoken->tokenValue = $_POST['securesubmitToken'];
        
        $details = new HpsTransactionDetails();
        $details->invoiceNumber = $this->context->cart->id;
        $details->memo = 'PrestaShop Order Number: '.(int)$this->context->cart->id;



		/** Currently saved plugin settings */
		/** This is the message show to the consumer if the rule is flagged */
		$fraud_message              = (string)  $this->getVelocityMsg();
		/** Maximum number of failures allowed before rule is triggered */
		$fraud_velocity_attempts    = (int)     $this->getVelocityLimit();
		/** Maximum amount of time in minutes to track failures. If this amount of time elapse between failures then the counter($HeartlandHPS_FailCount) will reset */
		$fraud_velocity_timeout     = (int)     $this->getVelocityTimeOut();
		/** Running count of failed transactions from the current IP*/
		$HeartlandHPS_FailCount     = (int)     $this->getVelocityCount();
		/** Defaults to true or checks actual settings for this plugin from $settings. If true the following settings are applied:
		$fraud_message
		 *
		$fraud_velocity_attempts
		 *
		$fraud_velocity_timeout
		 *
		 */
		$enable_fraud               = (bool)    $this->getVelocitySetting();


		try	{
			/**
			 * if fraud_velocity_attempts is less than the $HeartlandHPS_FailCount then we know
			 * far too many failures have been tried
			 */
			if ($enable_fraud && $HeartlandHPS_FailCount >= $fraud_velocity_attempts) {
				sleep(5);
				$issuerResponse = (string)$this->getVelocityLastResponse();
				throw new HpsException(sprintf('%s %s', $fraud_message, $issuerResponse));
			}

            $response = $chargeService->charge(
                $amount,
                "usd",
                $hpstoken,
                $cardHolder,
                false,
                $details);
                
            $ResponseMessage = 'Success';
		} catch(HpsException $e) {

			// if advanced fraud is enabled, increment the error count
			if ($enable_fraud) {
				$this->incVelocityCounter();
				if ($this->getVelocityCount() < $fraud_velocity_attempts) {
                    $this->setVelocityLastResponse($e->getMessage());
				}
			}
			$this->failPayment($this->getVelocityCount() . $e->getMessage());
            $ResponseMessage = 'Failure';
		}

		/* Log Transaction details */
		if (!isset($message)) {
			$order_status = (int) Configuration::get('HPS_PAYMENT_ORDER_STATUS');
			$message = $this->l('SecureSubmit Transaction Details:')."\n\n".
			$this->l('Transaction ID:').' '.$response->transactionId."\n";
		}
		else
			$order_status = (int)Configuration::get('PS_OS_ERROR');

		/* Create the PrestaShop order in database */
		$this->validateOrder((int)$this->context->cart->id, (int)$order_status, $amount, $this->displayName, $message, array(), null, false, $this->context->customer->secure_key);

		/** @since 1.5.0 Attach the SecureSubmit Transaction ID to this Order */
		if (version_compare(_PS_VERSION_, '1.5', '>='))
		{
			$new_order = new Order((int)$this->currentOrder);
			if (Validate::isLoadedObject($new_order))
			{
				$payment = $new_order->getOrderPaymentCollection();
				if (isset($payment[0]))
				{
					$payment[0]->transaction_id = pSQL($response->transactionId);
					$payment[0]->save();
				}
			}
		}

		/* Redirect the user to the order confirmation page / history */
		if (_PS_VERSION_ < 1.5)
			$redirect = __PS_BASE_URI__.'order-confirmation.php?id_cart='.(int)$this->context->cart->id.'&id_module='.(int)$this->id.'&id_order='.(int)$this->currentOrder.'&key='.$this->context->customer->secure_key;
		else
			$redirect = __PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.(int)$this->context->cart->id.'&id_module='.(int)$this->id.'&id_order='.(int)$this->currentOrder.'&key='.$this->context->customer->secure_key.'&paymentStatus='.$ResponseMessage;

		header('Location: '.$redirect);
		exit;
	}

	/**
	 * Function to log a failure message and redirect the user
	 * back to the payment processing screen with the error.
	 *
	 * @param string $message Error message to log and to display to the user
	 */
	private function failPayment($message)
	{
		$controller = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc.php' : 'order.php';
		$location=$this->context->link->getPageLink($controller).(strpos($controller, '?') !== false ? '&' : '?').'step=3&securesubmit_error='.base64_encode("There was a problem with your payment: ".$message).'#securesubmit_error';
		header('Location: '.$location);
		exit;
	}

	/**
	 * Check settings requirements to make sure the SecureSubmit's 
	 * API keys are set.
	 *
	 * @return boolean Whether the API Keys are set or not.
	 */
	public function checkSettings()
	{
		if (Configuration::get('HPS_MODE'))
			return Configuration::get('HPS_PUBLIC_KEY_LIVE') != '' && Configuration::get('HPS_SECRET_KEY_LIVE') != '';
		else
			return Configuration::get('HPS_PUBLIC_KEY_TEST') != '' && Configuration::get('HPS_SECRET_KEY_TEST') != '';
	}

	/**
	 * Check technical requirements to make sure the SecureSubmit's module will work properly
	 *
	 * @return array Requirements tests results
	 */
	public function checkRequirements()
	{
		$tests = array('result' => true);
		$tests['curl'] = array('name' => $this->l('PHP cURL extension must be enabled on your server'), 'result' => is_callable('curl_exec'));
		
        if (Configuration::get('HPS_MODE')) {
			$tests['ssl'] = array('name' => $this->l('SecureSubmit requires SSL to be enabled for production.'), 'result' => Configuration::get('PS_SSL_ENABLED') || (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off'));
        }
        
		$tests['currencies'] = array('name' => $this->l('The currency USD must be enabled on your store'), 'result' => Currency::exists('USD', 0));
		$tests['php52'] = array('name' => $this->l('Your server must run PHP 5.2 or greater'), 'result' => version_compare(PHP_VERSION, '5.2.0', '>='));
		$tests['configuration'] = array('name' => $this->l('You must set your SecureSubmit Public and Secret API Keys'), 'result' => $this->checkSettings());

		if (_PS_VERSION_ < 1.5)
		{
			$tests['backward'] = array('name' => $this->l('You are using the backward compatibility module'), 'result' => $this->backward, 'resolution' => $this->backward_error);
			$tmp = Module::getInstanceByName('mobile_theme');
			
            if ($tmp && isset($tmp->version) && !version_compare($tmp->version, '0.3.8', '>=')) {
				$tests['mobile_version'] = array('name' => $this->l('You are currently using the default mobile template, the minimum version required is v0.3.8').' (v'.$tmp->version.' '.$this->l('detected').' - <a target="_blank" href="http://addons.prestashop.com/en/mobile-iphone/6165-prestashop-mobile-template.html">'.$this->l('Please Upgrade').'</a>)', 'result' => version_compare($tmp->version, '0.3.8', '>='));
            }
		}

		foreach ($tests as $k => $test)
			if ($k != 'result' && !$test['result'])
				$tests['result'] = false;

		return $tests;
	}

	/**
	 * Display the SecureSubmit's module settings page
	 * for the user to set their API key pairs.
	 *
	 * @return string SecureSubmit settings page
	 */
	public function getContent()
	{
		$output = '';

		/* Update Configuration Values when settings are updated */
		if (Tools::isSubmit('SubmitSecureSubmit'))
		{
			$configuration_values = array(
				'HPS_MODE' => $_POST['securesubmit_mode'],
				'HPS_PUBLIC_KEY_TEST' => $_POST['securesubmit_public_key_test'],
				'HPS_PUBLIC_KEY_LIVE' => $_POST['securesubmit_public_key_live'], 
				'HPS_SECRET_KEY_TEST' => $_POST['securesubmit_secret_key_test'],
				'HPS_SECRET_KEY_LIVE' => $_POST['securesubmit_secret_key_live'], 
				'HPS_ENABLE_FRAUD' => $_POST['securesubmit_enable_fraud'],
				'HPS_FRAUD_MESSAGE' => $_POST['securesubmit_fraud_message'],
				'HPS_FRAUD_VELOCITY_ATTEMPTS' => $_POST['securesubmit_fraud_velocity_attempts'],
				'HPS_FRAUD_VELOCITY_TIMEOUT' => $_POST['securesubmit_fraud_velocity_timeout'],
				'HPS_PAYMENT_ORDER_STATUS' => (int)$_POST['securesubmit_payment_status']
			);

			foreach ($configuration_values as $configuration_key => $configuration_value) {
                $configuration_value = trim(filter_var($configuration_value, FILTER_SANITIZE_STRING));
				Configuration::updateValue($configuration_key, $configuration_value); // <<-- this can actually allow some code into the db.
            }
		}

		$requirements = $this->checkRequirements();

		$output .= '<link href="'.$this->_path.'securesubmit-settings.css" rel="stylesheet" type="text/css" media="all" />
		<div class="securesubmit-module-wrapper">
			'.(Tools::isSubmit('SubmitSecureSubmit') ? '<div class="conf confirmation">'.$this->l('Settings successfully saved').'<img src="http://www.prestashop.com/modules/'.$this->name.'.png?api_user='.urlencode($_SERVER['HTTP_HOST']).'" style="display: none;" /></div>' : '').'
			<div class="securesubmit-module-header">
				<a href="https://developer.heartlandpaymentsystems.com/SecureSubmit" target="_blank"><img class="logo" src="../modules/securesubmitpayment/img/heartland-logo.jpg" alt="Heartland Payment Systems Logo" width="140" height="140"></a>
				<a href="https://developer.heartlandpaymentsystems.com/SecureSubmit" target="_blank" class="btn right"><span>Sign up for free</span></a>
			</div>
			<section class="technical-checks">
				<h2>Technical Checks</h2>
				<div class="'.($requirements['result'] ? 'conf">'.$this->l('Good news! Everything looks to be in order. Start accepting credit card payments now.') :
				'warn">'.$this->l('Unfortunately, at least one issue is preventing you from using SecureSubmit. Please fix the issue and reload this page.')).'</div>
				<table cellspacing="0" cellpadding="0" class="securesubmit-technical">';
				foreach ($requirements as $k => $requirement)
					if ($k != 'result')
						$output .= '
						<tr>
							<td><img src="../img/admin/'.($requirement['result'] ? 'ok' : 'forbbiden').'.gif" alt="" /></td>
							<td>'.$requirement['name'].(!$requirement['result'] && isset($requirement['resolution']) ? '<br />'.Tools::safeOutput($requirement['resolution'], true) : '').'</td>
						</tr>';
				$output .= '
				</table>
			</section>
		<br />';

		/* If 1.4 and no backward, then leave */
		if (!$this->backward)
			return $output;

		$statuses = OrderState::getOrderStates((int)$this->context->cookie->id_lang);
		$output .= '
		<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" method="post">
			<section class="securesubmit-settings">
				<h2>API Key Mode</h2>
				<div class="account-mode container">
					<h4>Choose the payments account mode:</h4>
					<p>This determines whether the credit card payments are processed for real or not.</p>
					<input type="radio" name="securesubmit_mode" value="0"'.(!Configuration::get('HPS_MODE') ? ' checked="checked"' : '').' /><span>Test</span>
					<input type="radio" name="securesubmit_mode" value="1"'.(Configuration::get('HPS_MODE') ? ' checked="checked"' : '').' /><span>Live</span>
				</div>
				<h2>Set your API Keys</h2>
				<p>If you have not done so already, you can create an account by clicking the \'Sign up for free\' button above.<br />You can then obtain your API Keys from: Account Settings -> API Keys</p>

				<h2 style="color: #F60;">Test</h2>
				<table width="100%">
					<tr>
						<td width="50%;"><strong>Public Key</strong></td>
						<td><strong>Secret Key</string></td>
					</tr>
					<tr>
						<td><input style="width: 450px;" type="text" name="securesubmit_public_key_test" value="'.Tools::safeOutput(Configuration::get('HPS_PUBLIC_KEY_TEST')).'" /></td>
						<td><input style="width: 450px;" type="text" name="securesubmit_secret_key_test" value="'.Tools::safeOutput(Configuration::get('HPS_SECRET_KEY_TEST')).'" /></td>
					</tr>
				</table>

				<h2 style="color: #F60;">Live</h2>
				<table width="100%">
					<tr>
						<td width="50%;"><strong>Public Key</strong></td>
						<td><strong>Secret Key</string></td>
					</tr>
					<tr>
						<td><input style="width: 450px;" type="text" name="securesubmit_public_key_live" value="'.Tools::safeOutput(Configuration::get('HPS_PUBLIC_KEY_LIVE')).'" /></td>
						<td><input style="width: 450px;" type="text" name="securesubmit_secret_key_live" value="'.Tools::safeOutput(Configuration::get('HPS_SECRET_KEY_LIVE')).'" /></td>
					</tr>
				</table>
<!-- VELOCITY CHECKS-->
				<h2 style="color: #F60;">Velocity Limits</h2>

				<table width="100%">

					<tr>
					<td width="50%;"><strong>Velocity Settings</strong><br />&nbsp;</td>
					<td>
					<input type="radio" name="securesubmit_enable_fraud" value="0"'.(!$this->getVelocitySetting() ? ' checked="checked"' : '').' /> <span>Disabled</span>&nbsp;
					<input type="radio" name="securesubmit_enable_fraud" value="1"'.($this->getVelocitySetting() ? ' checked="checked"' : '').' /> <span>Enabled</span><br />&nbsp;</td>
					</tr>

					<tr>
					<td width="50%;"><strong>Display Message</strong><br />&nbsp;</td>
					<td><input style="width: 450px;" type="text" name="securesubmit_fraud_message"
						value="'.$this->getVelocityMsg().'" /><br />&nbsp;</td>
					</tr>

					<tr>
					<td width="50%;"><strong>How many failed attempts before blocking?</strong><br />&nbsp;</td>
					<td><input style="width: 450px;" type="text" name="securesubmit_fraud_velocity_attempts"
						value="'.$this->getVelocityLimit().'" /><br />&nbsp;</td>
					</tr>

					<tr>
					<td width="50%;"><strong>How long (in minutes) should we keep a tally of recent failures?</strong><br /><br />&nbsp;</td>
					<td><input style="width: 450px;" type="text" name="securesubmit_fraud_velocity_timeout"
						value="'.$this->getVelocityTimeOut().'" /><br />&nbsp;</td>
					</tr>
				</table>


                ';

				$statuses_options = array(array('name' => 'securesubmit_payment_status', 'label' => $this->l('Order status in case of sucessfull payment:'), 'current_value' => Configuration::get('HPS_PAYMENT_ORDER_STATUS')));
				foreach ($statuses_options as $status_options)
				{
					$output .= '
						<h4>'.$status_options['label'].'</h4>
						<div>
							<select name="'.$status_options['name'].'">';
								foreach ($statuses as $status)
									$output .= '<option value="'.(int)$status['id_order_state'].'"'.($status['id_order_state'] == $status_options['current_value'] ? ' selected="selected"' : '').'>'.Tools::safeOutput($status['name']).'</option>';
					$output .= '
							</select>
						</div>';
				}

				$output .= '<br>
				<div><input type="submit" class="settings-btn btn" name="SubmitSecureSubmit" value="Save Settings" /></div>

			</section>
			<div class="clear"></div>
			<br />

		</div>
		</form>';

		return $output;
	}

    /** If the Velocity checks are enabled
     * @return bool
     */
    private function getVelocitySetting(){
        $this->setVelocityTimer();
        return (bool)$this->get_setting("ENABLE_FRAUD", '1') ;
    }

    /** Gets the message to be displayed to the end user if the rule is triggered
     * @return string
     */
    private function getVelocityMsg(){
        return $this->get_setting("FRAUD_MESSAGE", 'Please contact us to complete the transaction.');
    }

    /** Number of minutes between attempts to retain failure counts
     * @return int
     */
    private function getVelocityTimeOut(){
        return (int) $this->get_setting("FRAUD_VELOCITY_TIMEOUT", '10');
    }

    /** Number of failures before the rule is applied
     * @return int
     */
    private function getVelocityLimit(){
        return (int) $this->get_setting("FRAUD_VELOCITY_ATTEMPTS", '3');
    }

    /** Sets the timer to the current time in seconds. Unix time stamp
     * @return mixed
     */
    private function setVelocityTimer(){
        $this->context->cookie->LastCheck = time();
        return $this->context->cookie->LastCheck;
    }

    /** Checks if the current settings have timed out
     * @return bool
     */
    private function isVelocityTimedOut(){
        return (bool) ($this->context->cookie->LastCheck < (time()-(ONE_MINUTE_IN_SECONDS*$this->getVelocityTimeOut())));
    }

    /** Returns the current if any count of Failed transactions
     * @return int
     */
    private function getVelocityCount(){
        return (int) $this->context->cookie->VelocityCount;
        }

    /** increment or reset the velocity counter. This counter is limited in duration by \SecureSubmitPayment::isVelocityTimedOut
     * @return int
     */
    private function incVelocityCounter(){
        if ($this->getVelocitySetting()){
            if ($this->isVelocityTimedOut()){
                $this->context->cookie->VelocityCount = 0;
            }
            else{
                $this->context->cookie->VelocityCount = $this->getVelocityCount() + 1;
            }
            $this->setVelocityTimer();
        }
        else{
            $this->context->cookie->VelocityCount = 0;
        }
        return (int) $this->getVelocityCount();
    }

    /** gets any response currently stored
     * @param $IssuerResponse
     */
    private function getVelocityLastResponse() {
        $re = '';
        if ($this->getVelocitySetting() && $this->isVelocityTimedOut()){
            $re = $this->context->cookie->IssuerResponse;
            $this->setVelocityTimer();
        }
        return $re;
    }

    /** Set the last issuer response. stores it so it can be displayed back to the user on subsequent failures
     * @return int
     */
    private function setVelocityLastResponse($IssuerResponse){
        $this->context->cookie->IssuerResponse = '';
        if ($this->getVelocitySetting()){
            $this->context->cookie->IssuerResponse = trim(filter_var($IssuerResponse, FILTER_SANITIZE_STRING));
        }
        return $this->getVelocityLastResponse();
    }

    /** Uses built in Prestashop API \ToolsCore::safeOutput(\ConfigurationCore::get) to get a setting but accepts a default value to be returned if not set
     * @param string $setting
     * @param string $default
     * @return string string
     */
    private function get_setting($setting, $default){
        $ret = trim(Tools::safeOutput(Configuration::get('HPS_' . $setting)));
        if ($ret === ''){
            $ret = $default;
        }
        return $ret;
    }
}
