<?php

require_once('simple_html_dom.php');

/**
 * Class to create and remove email accounts from Dreamhost Panel
 * 
 * @author Daniel Ribeiro <danielribeiro2001@gmail.com>
 */
class DHMailAPI {

	private $_cookieFile = '';
	private $_credentials = array();
	private $_logged = false;
	private $_errors = array();
	private $_securityKey = '';

	public function __construct($username, $password) {
		$this->_cookieFile = dirname(__FILE__) . '/browser.cookie'; // the script should be permission to write in this file
		$this->_credentials = array(
			'username'	=> $username,
			'password'	=> $password
		);
	}

	public function __destruct() {
		$this->_logout();		
		$this->_clearCookie();
	}

	public function getErrors() {
		return $this->_errors;
	}

	public function create($email, $password) {

		if(!$this->_logged)
			$this->_login();

		$parameters = array(
			'domain'				=> substr($email, strpos($email, '@') + 1),
			'tree'					=> 'mail.addresses',
			'current_step'			=> 'New',
			'next_step'				=> 'NewFinal',
			'security_key'			=> $this->_getSecurityKey(),
			'dest'					=> 'forward',
			'mailbox'				=> '1',
			'gecos'					=> str_replace('@', '-', $email),
			'alias'					=> substr($email, 0, strpos($email, '@')),
			'password1'				=> $password,
			'password2'				=> $password,
			'addresses'				=> '', // (email address to forward messages)
			'enable_quota'			=> '1',
			'hard_quota'			=> '200', // mailbox size in MB
			'notify_disk'			=> '0', // email daily warnings
			'max_messages'			=> '250', //remove messages when inbox reach
			'days'					=> '45', //Remove read messages from inbox older
			'rotate_new'			=> '', // Even remove unread messages
			'archive'				=> '', // archive messages
			'archive_folder'		=> 'old-messages', // folder name to archive messages
			'notify'				=> '' // Email me when messages are removed
		);

		$result = $this->_browser('https://panel.dreamhost.com/index.cgi', $parameters);
		if($this->_getMessageContent('div.errorbox', $result)) {
			$this->_errors = $this->_getMessageContent('div.error', $result, true);
			return false;
		}

		if($this->_getMessageContent('div.successbox', $result))
			return true;

		throw new Exception('Error creating email account', 1);
	}

	public function delete($email) {
		if(!$this->_logged)
			$this->_login();	

		$url = 'https://panel.dreamhost.com/index.cgi';
		$url.= '?tree=mail.addresses';
		$url.= '&odomain=' . substr($email, strpos($email, '@') + 1);
		$url.= '&current_step=Index&next_step=DeleteFinal';
		$url.= '&oalias=' . substr($email, 0, strpos($email, '@'));		
		$url.= '&security_key=' . $this->_getSecurityKey();

		$result = $this->_browser($url);

		if($this->_getMessageContent('div.errorbox', $result)) {
			$this->_errors = $this->_getMessageContent('div.error', $result, true);
			return false;
		}

		if($this->_getMessageContent('div.successbox', $result))
			return true;

		throw new Exception('Error removing email account', 1);
	}

	protected function _getSecurityKey() {
		if($this->_securityKey)
			return $this->_securityKey;

		$html = str_get_html($this->_browser('https://panel.dreamhost.com/index.cgi?tree=mail.addresses&current_step=Index&next_step=New'));
		$findElement = $html->find('input[name=security_key]');
		if(count($findElement) > 0)
			$this->_securityKey = $findElement[0]->value;
		else
			throw new Exception('Error getting the security_key', 1);
		
		return $this->_securityKey;
	}

	private function _browser($url, $postArray = array()) {
		$cookieFile =  $this->_cookieFile;

		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL				=> $url,
			CURLOPT_COOKIEJAR		=> $cookieFile,
			CURLOPT_COOKIEFILE		=> $cookieFile,
			CURLOPT_SSL_VERIFYPEER	=> 0,			
			CURLOPT_BINARYTRANSFER	=> true,
			CURLOPT_TIMEOUT			=> 10,
			CURLOPT_RETURNTRANSFER	=> true
		));

		if($postArray && count($postArray) > 0) {
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $postArray);
		}

		$result = curl_exec($curl);

		if($result === false)
			throw new Exception('Curl error: ' . curl_error($curl), 1);

		curl_close($curl);
		return $result;
	}

	private function _clearCookie() {
		if(file_exists($this->_cookieFile))
			unlink($this->_cookieFile);
	}

	protected function _login() {
		// Get the login page
		$this->_browser('https://panel.dreamhost.com/index.cgi');

		// Do the login
		$result = $this->_browser('https://panel.dreamhost.com/index.cgi', array(
			'username'	=> $this->_credentials['username'],
			'password'	=> $this->_credentials['password'],
			'Nscmd'		=> 'Nlogin'
		));

		if($errorMessage = $this->_getMessageContent('div.alert-error', $result))
			throw new Exception('Login error: ' . $errorMessage, 1);

		$this->_logged = true;
	}

	protected function _logout() {
		$this->_browser('https://panel.dreamhost.com/index.cgi?Nscmd=Nlogout');
	}

	protected function _getMessageContent($selector, $html, $all = false) {
		$html = str_get_html($html);
		$findElement = $html->find($selector);
		if($all) {
			$array = array();
			foreach($findElement as $item) {
				$message = strip_tags($item->__toString());
				if(!in_array($message, $array))
					array_push($array, $message);
			}
			return $array;
		} else {
			if(count($findElement) > 0)
				$message = strip_tags($findElement[0]->__toString());
			return isset($message) ? $message : false;			
		}
	}

}