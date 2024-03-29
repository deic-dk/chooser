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
	public $authUser = '';
	
	public $allowUpload = false;
	public $path = null;
	public $token = null;
	private $sharingOut = false;
	private $sharingOutAuthenticated = false;
	
	private function check_password($owner, $password, $storedPwHash){
		$forcePortable = (CRYPT_BLOWFISH != 1);
		$hasher = new \PasswordHash(8, $forcePortable);
		if(!($hasher->CheckPassword($password.\OC_Config::getValue('passwordsalt', ''), $storedPwHash))){
			\OC_Log::write('chooser','Password validation failed: '.$password.', hash: '.$storedPwHash.', owner: '.$owner, \OC_Log::WARN);
			return null;
		}
		return $owner;
	}

	public function __construct($baseuri) {
		$this->authUser = \OC_User::getUser();
		if(empty($this->authUser)){
			$this->authUser = $_SERVER['PHP_AUTH_USER'];
		}
		self::$baseUri = $baseuri;
		$reqUri = urldecode(\OCP\Util::getRequestUri());
		$reqPath = substr($reqUri, strlen(self::$baseUri));
		$reqPath = \OC\Files\Filesystem::normalizePath($reqPath);
		
		$token = preg_replace("/^\/*([^\/]+)\/*.*$/", "$1", $reqPath);
		$token = preg_replace("/^\/*([^\/]+)$/", "$1", $token);
		\OC_Log::write('chooser','Setting up share from token: '.$token, \OC_Log::WARN);
		if(!empty($token) && $token!=$reqPath && $baseuri==\OC::$WEBROOT."/public"){
			$res = $this->setupFromToken($token);
			if($res){
				return $res;
			}
		}
		
		// setupFromToken failed. This may or may not be a share from a group folder. Just try.
		$token = preg_replace("/^\/([^\/]+)\/([^\/]+)\/.*$/", "$2", $reqPath);
		$token = preg_replace("/^\/([^\/]+)\/([^\/]+)$/", "$2", $reqPath);
		$group = "";
		$group = preg_replace("/^\/([^\/]+)\/([^\/]+)\/.*$/", "$1", $reqPath);
		$group = preg_replace("/^\/([^\/]+)\/([^\/]+)$/", "$1", $reqPath);
		if($group==$reqPath){
			$group = "";
		}
		else{
			if(!empty($token) && $token!=$reqPath && $baseuri==\OC::$WEBROOT."/public"){
				return $this->setupFromToken($token, $group);
			}
		}
		
		if($baseuri==\OC::$WEBROOT."/sharingout"){
			$this->sharingOut = true;
			return $this->setupSharingout($reqPath);
		}
		return false;
	}
	
	private function setupSharingout($reqPath){
		$checkOwner = preg_replace('|^/([^/]+)/*.*|', '$1', $reqPath);
		if(strpos($checkOwner, '/')!==false || $checkOwner==$reqPath || empty($this->authUser)){
			return false;
		}
		elseif($this->authUser==$checkOwner){
			$this->userId = $checkOwner;
			$this->sharingOutAuthenticated = true;
			return true;
		}
		if(\OCA\FilesSharding\Lib::onServerForUser($this->authUser)){
			\OC_User::setUserId($this->authUser);
			\OC_Util::setUpFS($this->authUser);
			$shares = \OCA\Files\Share_files_sharding\Api::getFilesSharedWithMe();
			$sharesData = $shares->getData();
		}
		else{
			$sharesData = \OCA\FilesSharding\Lib::ws('getItemsSharedWith', array('user_id' => $_SERVER['PHP_AUTH_USER'],
					'itemType' => 'file'));
		}
		foreach($sharesData as $share) {
			
			$group = '';
			if(!empty($share['path']) && preg_match("|^/*user_group_admin/|", $share['path'])){
				$group = preg_replace("|^/*user_group_admin/([^/]+)/.*|", "$1", $share['path']);
				$sharepath = preg_replace('|^/*user_group_admin/[^/]+/*|', '', $share['path']);
			}
			else{
				$sharepath = preg_replace('|^/*files/|', '', $share['path']);
			}
			$sharename = preg_replace('|^.*/|', '', $sharepath);
			\OC_Log::write('share_auth','checking path '.$sharename.':'.$sharepath.':'.$checkOwner.
					':'.$reqPath.' : '.$group, \OC_Log::WARN);
			if($share['uid_owner']==$checkOwner && ($reqPath=='/'.$share['uid_owner'].'/'.$sharename ||
					strpos($reqPath, '/'.$share['uid_owner'].'/'.$sharename.'/')===0)){
				$this->userId = $share['uid_owner'];
				$this->sharingOutAuthenticated = true;
				\OCP\Util::writeLog('chooser', 'User OK: '. $checkOwner.':'.$this->authUser.
						':'.$this->userId, \OC_Log::WARN);
				break;
			}
		}
		if($this->userId!=null && trim($this->userId)!==''){
			\OC_Log::write('chooser','Permissions: '.$share['permissions'], \OC_Log::WARN);
			$this->allowUpload = (bool) ($share['permissions'] & \OCP\PERMISSION_CREATE);
		}
		else{
			return false;
		}
		//$this->currentUser = $this->userId;
		//\OC_User::setUserId($this->userId);
		return true;
	}
	
	private function setupFromToken($token, $group=""){
		if(!\OCP\App::isEnabled('files_sharding') || \OCA\FilesSharding\Lib::isMaster()){
			if(!empty($group)){
				\OC\Files\Filesystem::tearDown();
				$groupDir = '/'.$this->authUser.'/user_group_admin/'.$group;
				\OC\Files\Filesystem::init($this->authUser, $groupDir);
				$linkedItem = \OCP\Share::getShareByToken($token, false);
			}
			else{
				$linkedItem = \OCP\Share::getShareByToken($token, false);
			}
		}
		else{
			if(!empty($group)){
				$linkedItem = \OCA\FilesSharding\Lib::ws('getShareByToken', array('t'=>urlencode($token), 'g'=>urlencode($group)));
			}
			else{
				$linkedItem = \OCA\FilesSharding\Lib::ws('getShareByToken', array('t'=>$token));
			}
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
						array('linkItem'=>\OCP\JSON::encode($linkedItem), 'group'=>urlencode($group)), true, true);
			}
			if (isset($rootLinkItem['uid_owner'])) {
				\OCP\JSON::checkUserExists($rootLinkItem['uid_owner']);
				\OC_Util::tearDownFS();
				if(!empty($group)){
					\OC\Files\Filesystem::tearDown();
					$groupDir = '/'.$rootLinkItem['uid_owner'].'/user_group_admin/'.$group;
					\OC\Files\Filesystem::init($rootLinkItem['uid_owner'], $groupDir);
				}
				else{
					\OC_Util::setupFS($rootLinkItem['uid_owner']);
				}
				$this->token = $token;
				$this->path = \OC\Files\Filesystem::getPath($rootLinkItem['item_source']);
				$this->path = preg_replace("/^\//", "", $this->path);
				$linkedItem['path'] = $this->path;
				\OC_Log::write('chooser','Token: '.$token.', path: '.$this->path.', group: '.$group.', owner: '.$rootLinkItem['uid_owner'], \OC_Log::WARN);
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
				\OC_Log::write('chooser','Permissions: '.$linkedItem['permissions'], \OC_Log::WARN);
				$this->allowUpload = (bool) ($linkedItem['permissions'] & \OCP\PERMISSION_CREATE);
			}
		}
		else{
			\OC_Util::tearDownFS();
			return false;
		}
		$this->currentUser = $this->userId;
		\OC_User::setUserId($this->userId);
		//\OC_Util::setUpFS($this->userId);
		\OC_Log::write('chooser','userId: '.$this->userId, \OC_Log::WARN);
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
		\OC_Log::write('chooser','Validating: '.$this->userId, \OC_Log::WARN);
		if($this->sharingOut && $this->sharingOutAuthenticated){
			\OC_Util::tearDownFS();
			\OC_User::setUserId($this->authUser);
			return true;
		}
		elseif(!empty($this->userId) && \OC_User::userExists($this->userId)){
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
		\OC_Log::write('chooser','Authenticating: '.$this->userId, \OC_Log::WARN);
		if($this->sharingOut && $this->sharingOutAuthenticated){
			\OC_Util::tearDownFS();
			\OC_User::setUserId($this->authUser);
			return true;
		}
		elseif(!empty($this->userId) && \OC_User::userExists($this->userId)){
			$this->currentUser = $this->userId;
			\OC_User::setUserId($this->userId);
			\OC_Util::setUpFS($this->userId);
			\OC_Log::write('chooser','Authentication: all good for '.$this->userId, \OC_Log::WARN);
			return true;
		}
		else{
			return false;
		}

	}

} 
