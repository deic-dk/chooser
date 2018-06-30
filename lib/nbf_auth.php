<?php
/**
 * Copyright (c) 2013 Frederik Orellana.
 * No brute force authentication.
 * File based on oauth_ro_auth.php by Michiel de Jong <michiel@unhosted.org>.
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

require_once('lib/private/connector/sabre/auth.php');

class OC_Connector_Sabre_Auth_NBF extends OC_Connector_Sabre_Auth {
	//private $validTokens;
	//private $category;
	public function __construct() {
	}

	protected function validateUserPass($username, $password) {
		if (OC_User::isLoggedIn()) {
			OC_Util::setupFS(OC_User::getUser());
			return true;
		} else {
			$cache_key = 'login_attempts_'.$username;
			$login_attempts = 0;
			if(apc_exists($cache_key)){
				$login_attempts = apc_fetch($cache_key);
			}
			if($login_attempts>2){
				\OCP\Util::writeLog('login', 'Too many failed login attempts: '.$cache_key.'-->'.$login_attempts, \OC_Log::ERROR);
				die();
			}
			OC_Util::setUpFS();//login hooks may need early access to the filesystem
			if(OC_User::login($username, $password)) {
				OC_Util::setUpFS(OC_User::getUser());
				return true;
			}
			else{
				$login_attempts = $login_attempts + 1;
				\OCP\Util::writeLog('login', 'Login attempts: '.$cache_key.'-->'.$login_attempts, \OC_Log::ERROR);
				apc_store($cache_key, $login_attempts, 300);
				return false;
			}
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
		if (OC_User::handleApacheAuth() || OC_User::isLoggedIn()) {
			$user = OC_User::getUser();
			OC_Util::setupFS($user);
			$this->currentUser = $user;
			return true;
		}
		
		$parentsParent = class_parents($this)[1];
		return $parentsParent->authenticate($server, $realm);
	}

} 
