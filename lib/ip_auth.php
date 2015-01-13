<?php
/**
 * Copyright (c) 2013 Frederik Orellana.
 * IP authentication.
 * File based on oauth_ro_auth.php by Michiel de Jong <michiel@unhosted.org>.
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

require_once('apps/chooser/lib/lib_chooser.php');
require_once('3rdparty/Sabre/DAV/Auth/IBackend.php');
require_once('3rdparty/Sabre/DAV/Auth/Backend/AbstractBasic.php');

class OC_Connector_Sabre_Auth_ip_auth extends Sabre_DAV_Auth_Backend_AbstractBasic {
	//private $validTokens;
	//private $category;
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
		$user_id = OC_Chooser::checkIP();
		if($user_id != '' && OC_User::userExists($user_id)){
			$this->currentUser = $user_id;
			\OC_User::setUserId($user_id);
			OC_Util::setUpFS($user_id);
			return true;
		}
		else{
			return false;
		}
	}

	public function authenticate(Sabre_DAV_Server $server, $realm) {
		$user_id = OC_Chooser::checkIP();
		/*if($user_id == '' || !OC_User::userExists($user_id)){
			throw new Sabre_DAV_Exception_NotAuthenticated('Not a valid IP address / userid, ' . $user_id);
		}*/
		if($user_id != '' && OC_User::userExists($user_id)){
			$this->currentUser = $user_id;
			\OC_User::setUserId($user_id);
			OC_Util::setUpFS($user_id);
			$_SERVER['HTTP_USER_AGENT'] = "IP_PASS:".$_SERVER['HTTP_USER_AGENT'];
			return true;
		}
		else{
			//return parent::authenticate($server, $realm);
			return true;
		}
	}

} 
