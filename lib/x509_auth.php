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
			\OC_Log::write('chooser',"Got user_id ".$user_id, \OC_Log::WARN);
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
			\OC_Log::write('chooser',"Got user ".$user_id. ":".$_SERVER['PHP_AUTH_USER'], \OC_Log::WARN);
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
		if(/*empty($_SERVER['PHP_AUTH_USER']) ||*/ empty($_SERVER['SSL_CLIENT_VERIFY']) ||
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
				$_SERVER['REDIRECT_SSL_CLIENT_S_DN'].':'.
				$_SERVER['SSL_CLIENT_M_SERIAL'], \OC_Log::WARN);
		// Check admin access - we don't need to check serial: ScienceData host certificates are trusted
		if(!empty($user) && \OC_User::userExists($user) && \OCP\App::isEnabled('files_sharding')){
			if(\OCA\FilesSharding\Lib::checkAdminCert()){
				return $user;
			}
		}
		// Check that the serial of the received certificate matches the one of the currently active one
		// See https://cweiske.de/tagebuch/ssl-client-certificates.htm
		$checkUser = \OC_Chooser::getUserFromSubject($clientDN, $user);
		if(empty($checkUser)){
			return "";
		}
		$checkSerial = \OC_Chooser::getSDCertSerial($checkUser);
		if($_SERVER['SSL_CLIENT_M_SERIAL']!=$checkSerial){
			\OC_Log::write('chooser','Certificate serials mismatch: for '.$checkUser.' : '.
					$_SERVER['SSL_CLIENT_M_SERIAL'].' != '.$checkSerial, \OC_Log::ERROR);
		}
		
		// Check if the request is from a host trusted to relay DN in header.
		// If so, and if the DN header is set, set $clientDN with this instead.
		if(!empty($clientDN) && \OCP\App::isEnabled('files_sharding')){
			$dnHeaderDN = \OC_Chooser::checkCertRelay($clientDN);
			if(!empty($dnHeaderDN)){
				$clientDN = $dnHeaderDN;
			}
		}

		// Check the client subject
		$user = \OC_Chooser::getUserFromSubject($clientDN, $user);
		if(!empty($user)){
			return $user;
		}
		return "";
	}
	

} 
