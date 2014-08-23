<?php
/**
 * Copyright (c) 2014 Frederik Orellana.
 * Authentication according to sharing of a given file or folder.
 * Based on apps/files_sharing/public.php.
 */

require_once('3rdparty/Sabre/DAV/Auth/IBackend.php');
require_once('3rdparty/Sabre/DAV/Auth/Backend/AbstractBasic.php');

class OC_Connector_Sabre_Auth_share_auth extends Sabre_DAV_Auth_Backend_AbstractBasic {

	private static $baseUri = "/remote.php/mydav";
	public $userId = '';
	
	public $allowUpload = false;
	public $path = null;
	public $token = null;

	
	private function check_password($owner, $password, $storedPwHash){
		$forcePortable = (CRYPT_BLOWFISH != 1);
		$hasher = new PasswordHash(8, $forcePortable);
		if(!($hasher->CheckPassword($password.OC_Config::getValue('passwordsalt', ''), $storedPwHash))){
			return null;
		}
		return $owner;
	}

	public function __construct($baseuri) {
		self::$baseUri = $baseuri;
		$reqUri = OCP\Util::getRequestUri();
		$reqPath = substr($reqUri, strlen(self::$baseUri));
		$reqPath = \OC\Files\Filesystem::normalizePath($reqPath);
		$token = preg_replace("/^\/([^\/]*)\/.*$/", "$1", $reqPath);
		$token = preg_replace("/^\/([^\/]*)$/", "$1", $token);
		$linkItem = OCP\Share::getShareByToken($token, false);
		if (is_array($linkItem) && isset($linkItem['uid_owner'])) {
			// seems to be a valid share
			$rootLinkItem = OCP\Share::resolveReShare($linkItem);
			if (isset($rootLinkItem['uid_owner'])) {
				OCP\JSON::checkUserExists($rootLinkItem['uid_owner']);
				OC_Util::tearDownFS();
				OC_Util::setupFS($rootLinkItem['uid_owner']);
				$this->token = $token;
				$this->path = \OC\Files\Filesystem::getPath($linkItem['file_source']);
				$this->path = preg_replace("/^\//", "", $this->path);
				OC_Log::write('chooser','Token: '.$token.', path: '.$this->path.', owner: '.$rootLinkItem['uid_owner'], OC_Log::INFO);
			}
		}
		if($this->path==null || !isset($linkItem['item_type'])){
			return;
		}
		// $linkItem['share_with'] holds the hashed password for $linkItem['share_type'] == OCP\Share::SHARE_TYPE_LINK
		// - which is the share_type we're concerned with here
		if(isset($linkItem['share_with'])){
			if(isset($_SERVER['PHP_AUTH_USER'])){
				// We don't care what username is supplied - the uid will be set to that of the one owning the shared item
				$this->userId = $this->check_password($linkItem['uid_owner'], $_SERVER['PHP_AUTH_PW'], $linkItem['share_with']);
			}
		}
		else{
			$this->userId = $linkItem['uid_owner'];
		}
		if($this->userId!=null && trim($this->userId)!==''){
			if(OC_Appconfig::getValue('core', 'shareapi_allow_public_upload', 'yes')==='yes'){
				OC_Log::write('chooser','Permissions: '.$linkItem['permissions'], OC_Log::INFO);
				$this->allowUpload = (bool) ($linkItem['permissions'] & OCP\PERMISSION_CREATE);
			}
		}
		$this->currentUser = $this->userId;
		\OC_User::setUserId($this->userId);
		OC_Util::setUpFS($this->userId);
		OC_Log::write('chooser','userId: '.$this->userId, OC_Log::DEBUG);
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
		OC_Log::write('chooser','Validating: '.$this->userId, OC_Log::DEBUG);
		if($this->userId != '' && OC_User::userExists($this->userId)){
			$this->currentUser = $this->userId;
			\OC_User::setUserId($this->userId);
			OC_Util::setUpFS($this->userId);
			return true;
		}
		else{
			return false;
		}
	}

	public function authenticate(Sabre_DAV_Server $server, $realm) {
		OC_Log::write('chooser','Authenticating: '.$this->userId, OC_Log::DEBUG);
		if($this->userId != '' && OC_User::userExists($this->userId)){
			$this->currentUser = $this->userId;
			\OC_User::setUserId($this->userId);
			OC_Util::setUpFS($this->userId);
			return true;
		}
		else{
			return false;
		}

	}

} 
