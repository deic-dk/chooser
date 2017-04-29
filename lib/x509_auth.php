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

class X509 extends AbstractBasic {
	
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
			\OC_Log::write('chooser','user_id '.$_SERVER['PHP_AUTH_USER'].':'.$_SERVER['PHP_AUTH_PW'],\OC_Log::INFO);
			return false;
		}
		$user_id = self::checkCert();
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
	
	public function checkUserPass($username, $password) {
		return $this->validateUserPass($username, $password);
	}

	public function authenticate(\Sabre\DAV\Server $server, $realm) {
		if(!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])){
			\OC_Log::write('chooser','user_id '.$_SERVER['PHP_AUTH_USER'].':'.$_SERVER['PHP_AUTH_PW'],\OC_Log::INFO);
			return false;
		}
		$user_id = self::checkCert();
		if($user_id != '' && \OC_User::userExists($user_id)){
			$this->currentUser = $user_id;
			\OC_User::setUserId($user_id);
			\OC_Util::setUpFS($user_id);
			return true;
		}
		else{
			return true;
		}
	}
	
	public static function checkCert(){
		if(empty($_SERVER['PHP_AUTH_USER']) ||
			empty($_SERVER['SSL_CLIENT_VERIFY']) ||
			$_SERVER['SSL_CLIENT_VERIFY']!='SUCCESS' && $_SERVER['SSL_CLIENT_VERIFY']!='NONE'){
			return "";
		}
		$user = $_SERVER['PHP_AUTH_USER'];
		$issuerDN = !empty($_SERVER['SSL_CLIENT_I_DN'])?$_SERVER['SSL_CLIENT_I_DN']:
			(!empty($_SERVER['REDIRECT_SSL_CLIENT_I_DN'])?$_SERVER['REDIRECT_SSL_CLIENT_I_DN']:'');
		$clientDN = !empty($_SERVER['SSL_CLIENT_S_DN'])?$_SERVER['SSL_CLIENT_S_DN']:
			(!empty($_SERVER['REDIRECT_SSL_CLIENT_S_DN'])?$_SERVER['REDIRECT_SSL_CLIENT_S_DN']:'');
		if(empty($clientDN) || empty($issuerDN)){
			return "";
		}
		\OC_Log::write('chooser','Checking cert '.$_SERVER['PHP_AUTH_USER'].':'.
				$_SERVER['SSL_CLIENT_VERIFY'].':'.$_SERVER['REDIRECT_SSL_CLIENT_I_DN'].':'.
				$_SERVER['REDIRECT_SSL_CLIENT_S_DN'], \OC_Log::WARN);
		// Check admin access
		if(!empty($user) && \OC_User::userExists($user) && \OCP\App::isEnabled('files_sharding')){
			if(\OCA\FilesSharding\Lib::checkCert()){
				return $user;
			}
		}
		// Check that client DN starts with the issuer DN - very rough spoofing protection
		$issuerCheckStr = preg_replace('|CN=[^,]*,|', '', $issuerDN);
		if(strpos($clientDN, $issuerCheckStr)==false){
			return "";
		}
		$clientDNArr = explode(',', $clientDN);
		$clientDNwSlashes = '/'.implode('/', array_reverse($clientDNArr));
		$index = 0;
		while($index<\OC_Chooser::$MAX_CERTS){
			$subject = \OCP\Config::getUserValue($user, 'chooser', 'ssl_certificate_subject_'.$index);
			\OC_Log::write('chooser','Checking subject '.$subject.'<->'.$clientDNwSlashes, \OC_Log::WARN);
			if(!empty($subject) && $subject===$clientDNwSlashes){
				\OC_Log::write('chooser','Subject OK', \OC_Log::WARN);
				return $user;
			}
			++$index;
		}
		return "";
	}
	

} 
