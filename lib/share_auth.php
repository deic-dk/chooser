<?php
/**
 * Copyright (c) 2014 Frederik Orellana.
 * Authentication according to sharing of a given file or folder.
 * Based on apps/files_sharing/public.php.
 */

namespace Sabre\DAV\Auth\Backend;

require_once('3rdparty/sabre/dav/lib/Sabre/DAV/Auth/Backend/BackendInterface.php');
require_once('3rdparty/sabre/dav/lib/Sabre/DAV/Auth/Backend/AbstractBasic.php');

class Share extends AbstractBasic {

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
		$reqUri = \OCP\Util::getRequestUri();
		$reqPath = substr($reqUri, strlen(self::$baseUri));
		$reqPath = \OC\Files\Filesystem::normalizePath($reqPath);
		$token = preg_replace("/^\/([^\/]*)\/.*$/", "$1", $reqPath);
		$token = preg_replace("/^\/([^\/]*)$/", "$1", $token);
		if(empty($token)){
			return false;
		}
		if(!\OCP\App::isEnabled('files_sharding') || \OCA\FilesSharding\Lib::isMaster()){
			$linkedItem = \OCP\Share::getShareByToken($token, false);
		}
		else{
			$linkedItem = \OCA\FilesSharding\Lib::ws('getShareByToken', array('t'=>$token));
		}
		if(empty($linkedItem)){
			return false;
		}
		
		\OCP\Util::writeLog('chooser', 'Got share by token: '. $token . '-->' . serialize($linkedItem), \OC_Log::WARN);
		if (isset($linkedItem) && is_array($linkedItem) && isset($linkedItem['uid_owner'])) {
			// seems to be a valid share
			if(!\OCP\App::isEnabled('files_sharding')){
				$rootLinkItem = \OCP\Share::resolveReShare($linkedItem);
			}
			elseif(\OCA\FilesSharding\Lib::isMaster()){
				$rootLinkItem = \OCA\FilesSharding\Lib::resolveReShare($linkedItem);
			}
			else{
				$rootLinkItem = \OCA\FilesSharding\Lib::ws('resolveReShare',
					array('linkItem'=>\OCP\JSON::encode($linkedItem)));
			}
			if (isset($rootLinkItem['uid_owner'])) {
				\OCP\JSON::checkUserExists($rootLinkItem['uid_owner']);
				\OC_Util::tearDownFS();
				\OC_Util::setupFS($rootLinkItem['uid_owner']);
				$this->token = $token;
				$this->path = \OC\Files\Filesystem::getPath($rootLinkItem['item_source']);
				$this->path = preg_replace("/^\//", "", $this->path);
				\OC_Log::write('chooser','Token: '.$token.', path: '.$this->path.', owner: '.$rootLinkItem['uid_owner'], \OC_Log::WARN);
			}
		}
		if($this->path==null || !isset($linkedItem['item_type'])){
			return false;
		}
		// $linkedItem['share_with'] holds the hashed password for $linkedItem['share_type'] == \OCP\Share::SHARE_TYPE_LINK
		// - which is the share_type we're concerned with here
		if(isset($linkedItem['share_with'])){
			if(isset($_SERVER['PHP_AUTH_USER'])){
				// We don't care what username is supplied - the uid will be set to that of the one owning the shared item
				$this->userId = $this->check_password($linkedItem['uid_owner'], $_SERVER['PHP_AUTH_PW'], $linkedItem['share_with']);
			}
		}
		else{
			$this->userId = $linkedItem['uid_owner'];
		}
		if($this->userId!=null && trim($this->userId)!==''){
			if(\OC_Appconfig::getValue('core', 'shareapi_allow_public_upload', 'yes')==='yes'){
				\OC_Log::write('chooser','Permissions: '.$linkedItem['permissions'], \OC_Log::INFO);
				$this->allowUpload = (bool) ($linkedItem['permissions'] & \OCP\PERMISSION_CREATE);
			}
		}
		$this->currentUser = $this->userId;
		\OC_User::setUserId($this->userId);
		\OC_Util::setUpFS($this->userId);
		\OC_Log::write('chooser','userId: '.$this->userId, \OC_Log::WARN);
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
		\OC_Log::write('chooser','Validating: '.$this->userId, \OC_Log::WARN);
		if($this->userId != '' && \OC_User::userExists($this->userId)){
			$this->currentUser = $this->userId;
			\OC_User::setUserId($this->userId);
			\OC_Util::setUpFS($this->userId);
			return true;
		}
		else{
			return false;
		}
	}

	public function authenticate(\Sabre\DAV\Server $server, $realm) {
		\OC_Log::write('chooser','Authenticating: '.$this->userId, \OC_Log::INFO);
		if($this->userId != '' && \OC_User::userExists($this->userId)){
			$this->currentUser = $this->userId;
			//\OC_User::setUserId($this->userId);
			\OC_Util::setUpFS($this->userId);
			\OC_Log::write('chooser','Authentication: all good for '.$this->userId, \OC_Log::WARN);
			return true;
		}
		else{
			return false;
		}

	}

} 
