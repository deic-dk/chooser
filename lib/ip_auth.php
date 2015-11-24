<?php
/**
 * Copyright (c) 2013 Frederik Orellana.
 * IP authentication.
 * File based on oauth_ro_auth.php by Michiel de Jong <michiel@unhosted.org>.
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */
namespace Sabre\DAV\Auth\Backend;

require_once('apps/chooser/lib/lib_chooser.php');
require_once('3rdparty/sabre/dav/lib/Sabre/DAV/Auth/Backend/BackendInterface.php');
require_once('3rdparty/sabre/dav/lib/Sabre/DAV/Auth/Backend/AbstractBasic.php');

class IP extends AbstractBasic {
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
			if(isset($_SERVER['PHP_AUTH_USER'])){
			OC_Log::write('chooser','user_id '.$_SERVER['PHP_AUTH_USER'],OC_Log::INFO);
			return false;
		}
		$user_id = \OC_Chooser::checkIP();
		if($user_id != '' && \OC_User::userExists($user_id)){
			$this->currentUser = $user_id;
			\OC_User::setUserId($user_id);
			\OC_Util::setUpFS($user_id);
			return true;
		}
		else{
			return false;
		}
	}

	public function authenticate(\Sabre\DAV\Server $server, $realm) {
		if(isset($_SERVER['PHP_AUTH_USER'])){
			OC_Log::write('chooser','user_id '.$_SERVER['PHP_AUTH_USER'],OC_Log::INFO);
			return false;
		}
		$user_id = \OC_Chooser::checkIP();
		/*if($user_id == '' || !\OC_User::userExists($user_id)){
			throw new \Sabre\DAV\Exception\NotAuthenticated('Not a valid IP address / userid, ' . $user_id);
		}*/
		if($user_id != '' && \OC_User::userExists($user_id)){
			$this->currentUser = $user_id;
			\OC_User::setUserId($user_id);
			\OC_Util::setUpFS($user_id);
			// Uncomment this to unhide /Data for clients from the local subnet
			//$_SERVER['HTTP_USER_AGENT'] = "IP_PASS:".$_SERVER['HTTP_USER_AGENT'];
			return true;
		}
		else{
			//return parent::authenticate($server, $realm);
			return true;
		}
	}

} 
