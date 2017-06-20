<?php 

require_once('DHMailAPI.php');

// Create a new email account
$mailApi = new DHMailAPI('your-panel-account-login@domain.com', 'your-password');
if($mailApi->create('email-account@yourdomain.com', 'email-account-password'))
	echo 'Email account created' . PHP_EOL;	
else
	print_r($mailApi->getErrors());
	
// Deleting email account
$mailApi = new DHMailAPI('your-panel-account-login@domain.com', 'your-password');
if($mailApi->delete('email-account@yourdomain.com'))
	echo 'Email deleted' . PHP_EOL;
else
	print_r($mailApi->getErrors());