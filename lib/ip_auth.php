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
		if(!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])){
			\OC_Log::write('chooser','user_id '.$_SERVER['PHP_AUTH_USER'],\OC_Log::INFO);
			return false;
		}
		$headers = apache_request_headers();
		if(\OC_Chooser::$dn_header==null){
			\OC_Chooser::$dn_header = \OCP\Config::getSystemValue('dn_header', '');
		}
		$headerDN = trim($headers[\OC_Chooser::$dn_header]);
		if(!empty($headerDN) /*If SSL-CLIENT-DN is provided, x509_auth should be used*/){
			return false;
		}
		$user_id = \OC_Chooser::checkIP();
		if($user_id != '' && \OC_User::userExists($user_id)){
			if(!empty($_SERVER['PHP_AUTH_USER']) && $user_id != $_SERVER['PHP_AUTH_USER']){
				throw new \Sabre\DAV\Exception\NotAuthenticated('Username or password does not match');
			}
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
		if(!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])){
			\OC_Log::write('chooser','user_id '.$_SERVER['PHP_AUTH_USER'],\OC_Log::INFO);
			return false;
		}
		$headers = apache_request_headers();
		if(\OC_Chooser::$dn_header==null){
			\OC_Chooser::$dn_header = \OCP\Config::getSystemValue('dn_header', '');
		}
		$headerDN = trim($headers[\OC_Chooser::$dn_header]);
		if(!empty($headerDN) /*If SSL-CLIENT-DN is provided, x509_auth should be used*/){
			return false;
		}
		$user_id = \OC_Chooser::checkIP();
		\OC_Log::write('chooser','user_id NOW '.$user_id.' : '.$_SERVER['PHP_AUTH_USER'],\OC_Log::INFO);
		/*if($user_id == '' || !\OC_User::userExists($user_id)){
			throw new \Sabre\DAV\Exception\NotAuthenticated('Not a valid IP address / userid, ' . $user_id);
		}*/

		if($user_id != '' && \OC_User::userExists($user_id)){
			if(!empty($_SERVER['PHP_AUTH_USER']) && $user_id != $_SERVER['PHP_AUTH_USER']){
				throw new \Sabre\DAV\Exception\NotAuthenticated('Username or password does not match');
			}
			$this->currentUser = $user_id;
			\OC_User::setUserId($user_id);
			\OC_Util::setUpFS($user_id);
			// Uncomment this to unhide /Data for clients from the local subnet
			//$_SERVER['HTTP_USER_AGENT'] = "IP_PASS:".$_SERVER['HTTP_USER_AGENT'];
			return true;
		}
		else{
			//return parent::authenticate($server, $realm);
			//return true;
			//throw new \Sabre\DAV\Exception\NotAuthenticated('Authentication failed');
			return false;
		}
	}

} 
