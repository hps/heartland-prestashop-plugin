<?php

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../init.php');
include(dirname(__FILE__).'/securesubmitpayment.php');

if (!defined('_PS_VERSION_'))
	exit;

$securesubmit = new SecureSubmitPayment();
if ($securesubmit->active && isset($_POST['securesubmitToken']))
	$securesubmit->processPayment($_POST['securesubmitToken']);
else
	die('Token required, please check for any Javascript error on the payment page.');