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
	
	public function __construct() {
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
		if(empty($device_tokens)){
			return false;
		}
		$forcePortable = (CRYPT_BLOWFISH != 1);
		$hasher = new PasswordHash(8, $forcePortable);
		foreach($device_tokens as $device_name=>$storedHash){
			if($hasher->CheckPassword($password . OC_Config::getValue('passwordsalt', ''), $storedHash)){
				$user_id = $username;
				break;
			}
		}
		if(!empty($user_id) && \OC_User::userExists($user_id)){
			$this->currentUser = $user_id;
			\OC_User::setUserId($user_id);
			\OC_Util::setUpFS($user_id);
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
		return \Sabre\DAV\Auth\Backend\AbstractBasic::authenticate($server, $realm);
	}

}
