<?php
/**
 * Copyright (c) 2016 Frederik Orellana.
 * Client X.509 certificate authentication.
 * File based on oauth_ro_auth.php by Michiel de Jong <michiel@unhosted.org>.
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */
namespace Sabre\DAV\Auth\Backend;

require_once('3rdparty/sabre/dav/lib/Sabre/DAV/Auth/Backend/BackendInterface.php');
require_once('3rdparty/sabre/dav/lib/Sabre/DAV/Auth/Backend/AbstractBasic.php');

class Device extends AbstractBasic {
	
	private $ocs = false;
	private $sharingOut = false;
	private $baseuri = false;
	
	public function __construct($_baseuri) {
		if(!empty($_baseuri)){
			$this->baseuri = $_baseuri;
			if($this->baseuri==\OC::$WEBROOT."/sharingout"){
				$this->sharingOut = true;
			}
		}
		return true;
	}

	/**
	 * Validates a username and password
	 *
	 * This method should return true or false depending on if login
	 * succeeded.
	 *
	 * @return bool
	 */
	protected function validateUserPass($username, $password) {
		$device_tokens = \OC_Chooser::getDeviceTokens($username);
		\OCP\Util::writeLog('chooser', 'Validating password for user '.$username.' : '.
				$_SERVER['REQUEST_URI'], \OC_Log::INFO);
		if(empty($device_tokens)){
			return false;
		}
		$forcePortable = (CRYPT_BLOWFISH != 1);
		$hasher = new \PasswordHash(8, $forcePortable);
		foreach($device_tokens as $device_name=>$storedHash){
			\OCP\Util::writeLog('chooser', 'Checking '.$username.' : '.$storedHash.' : '.
					$password, \OC_Log::DEBUG);
			if($hasher->CheckPassword($password . \OC_Config::getValue('passwordsalt', ''), $storedHash)){
				$user_id = $username;
				break;
			}
		}
		if(!empty($user_id) && (\OC_User::userExists($user_id) || $this->ocs)){
			if($this->ocs){
				\OC_User::useBackend('database');
				//\OC_User::setupBackends();
				\OC::$session->set('SID_CREATED', time());
				\OC::$session->set('LAST_ACTIVITY', time());
				\OC_User::handleApacheAuth();
			}
			// This is not used. The idea was to use it with iOS push, but apparently device id is something else in that context.
			if(!empty($device_name)){
				\OC::$session->set('DEVICE_ID', preg_replace('|^device_token_|', '', $device_name));
			}
			$this->currentUser = $user_id;
			\OC_User::setUserId($user_id);
			\OC_Util::setUpFS($user_id);
			\OCP\Util::writeLog('chooser', 'Validated password '.\OC_User::isLoggedIn().
					' : '.$device_name.'=>'.$storedHash .' for '.$username.' : '.
					$_SERVER['REQUEST_URI'].' : '.\OC::$session->get('DEVICE_ID'), \OC_Log::INFO);
			return true;
		}
		else{
			return false;
		}
	}
	
	public function checkUserPass($username, $password) {
		return $this->validateUserPass($username, $password);
	}
	
	public function authenticate(\Sabre\DAV\Server $server, $realm) {
		
		$result = $this->auth($server, $realm);
		
		// close the session - right after authentication there is not need to write to the session any more
		// Well, we do need just that.
		//\OC::$session->close();
		
		return $result;
	}
	
	private function auth(\Sabre\DAV\Server $server, $realm) {
		if (\OC_User::handleApacheAuth() || \OC_User::isLoggedIn()) {
			$user = \OC_User::getUser();
			\OC_Util::setupFS($user);
			$this->currentUser = $user;
			return true;
		}
		if($this->sharingOut){
			return false;
		}
		return \Sabre\DAV\Auth\Backend\AbstractBasic::authenticate($server, $realm);
	}
	
	public static function login($params){
		if(empty($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_PW'])){
			return false;
		}
		$params['useCustomSession'] = true;
		self::handleAuthHeaders();
		\OCP\Util::writeLog('files_sharding', 'LOGIN INFO '.$_SERVER['PHP_AUTH_USER'].
				":".":".$_SERVER['REQUEST_URI'], \OC_Log::INFO);
		require_once '3rdparty/phpass/PasswordHash.php';
		$authBackendDevice = new Device(null);
		$authBackendDevice->ocs = true;
		$authBackendDevice->checkUserPass($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
		return true;
	}
	
	private static function handleAuthHeaders() {
		//copy http auth headers for apache+php-fcgid work around
		if (isset($_SERVER['HTTP_XAUTHORIZATION']) && !isset($_SERVER['HTTP_AUTHORIZATION'])) {
			$_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['HTTP_XAUTHORIZATION'];
		}
		
		// Extract PHP_AUTH_USER/PHP_AUTH_PW from other headers if necessary.
		$vars = array(
				'AUTHORIZATION', // apache+php-cgi work around
				'HTTP_AUTHORIZATION', // apache+php-cgi work around
				'REDIRECT_HTTP_AUTHORIZATION', // apache+php-cgi alternative
		);
		foreach ($vars as $var) {
			if (isset($_SERVER[$var]) && preg_match('/Basic\s+(.*)$/i', $_SERVER[$var], $matches)) {
				list($name, $password) = explode(':', base64_decode($matches[1]), 2);
				$_SERVER['PHP_AUTH_USER'] = $name;
				$_SERVER['PHP_AUTH_PW'] = $password;
				break;
			}
		}
	}

}
