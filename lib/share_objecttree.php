<?php

require_once 'chooser/lib/sharingin_directory.php';
require_once 'chooser/lib/sharingout_directory.php';
require_once 'chooser/lib/favorites_directory.php';

class Share_ObjectTree extends \OC\Connector\Sabre\ObjectTree {

	public $allowUpload = true;
	public $auth_token = null;
	public $auth_path = null;
	public $sharingIn = false;
	public $sharingOut = false;
	public $sharingInOut = false;
	public $favorites = false;
	public $usage = false;
	private $auth_user;
	
	public function fixPath(&$path){
		if($this->auth_token!=null && $this->auth_path!=null){
			OC_Log::write('chooser','path, auth_token: auth_path: '.$path.", ".$this->auth_token.":".$this->auth_path, OC_Log::INFO);
			$path = preg_replace("/^".$this->auth_token."/", $this->auth_path, $path);
		}
	}
	
	public function init(\Sabre\DAV\ICollection $rootNode, \OC\Files\View $view, \OC\Files\Mount\Manager $mountManager) {
		$this->rootNode = $rootNode;
		$this->fileView = $view;
		$this->mountManager = $mountManager;
		if(empty($this->auth_user)){
			$this->auth_user = \OC_User::getUser();
			if(!empty($_SERVER['PHP_AUTH_USER'])){
				$this->auth_user = $_SERVER['PHP_AUTH_USER'];
			}
		}
	}
	
	public function sharingInInit() {
		if(empty($this->auth_user)){
			$this->auth_user = \OC_User::getUser();
			if(!empty($_SERVER['PHP_AUTH_USER'])){
				$this->auth_user = $_SERVER['PHP_AUTH_USER'];
			}
		}
		OC_Log::write('chooser','Creating sharingin root dir', OC_Log::WARN);
		$this->rootNode = new \OC_Connector_Sabre_Sharingin_Directory();
	}
	
	public function sharingOutInit() {
		if(empty($this->auth_user)){
			$this->auth_user = \OC_User::getUser();
			if(!empty($_SERVER['PHP_AUTH_USER'])){
				$this->auth_user = $_SERVER['PHP_AUTH_USER'];
			}
		}
		OC_Log::write('chooser','Creating sharingout root dir', OC_Log::WARN);
		$this->rootNode = new \OC_Connector_Sabre_Sharingout_Directory();
	}
	
	public function favoritesInit() {
		OC_Log::write('chooser','Creating favorites root dir', OC_Log::WARN);
		$this->rootNode = new \OC_Connector_Sabre_Favorites_Directory();
	}
	
	public function getNodeForPath($path) {
		\OC_Log::write('chooser','Getting node for '.$path.':'.$this->sharingOut, \OC_Log::INFO);
		
		if($this->allowUpload==false &&
		(strtolower($_SERVER['REQUEST_METHOD'])=='mkcol' || strtolower($_SERVER['REQUEST_METHOD'])=='put' ||
		strtolower($_SERVER['REQUEST_METHOD'])=='move' || strtolower($_SERVER['REQUEST_METHOD'])=='delete' ||
		strtolower($_SERVER['REQUEST_METHOD'])=='proppatch')){
			throw new \Sabre\DAV\Exception\Forbidden($_SERVER['REQUEST_METHOD'].' not allowed. '.$this->allowUpload);
		}
	
		$this->fixPath($path);

		$path = trim($path, '/');
		if (isset($this->cache[$path])) {
			return $this->cache[$path];
		}

		// Is it the root node?
		if (!strlen($path)) {
			return $this->rootNode;
		}
		$filepath = $path;
		if(isset($this->sharingIn) && $this->sharingIn){
			// First deal with sharingin/some.user@inst.dk/
			$shareeRoot = false;
			if(preg_match('|^[^/]+$|', $path)){
				$shareeRoot = true;
			}
			//else
			// Now deal with haringin/some.user@inst.dk/some_share
			OC_Log::write('chooser','Creating sharingin sharee dir '.$path, OC_Log::WARN);
			$shares = \OCA\Files\Share_files_sharding\Api::getFilesSharedWithMe();
			$found = false;
			foreach($shares->getData() as $share) {
				if(strpos($path, $share['uid_owner'].'/')!==0 && $path!=$share['uid_owner']){
					continue;
				}
				if($shareeRoot){
					$found = true;
					break;
				}
				$group = '';
				if(!empty($share['path']) && preg_match("|^/*user_group_admin/|", $share['path'])){
					$group = preg_replace("|^/*user_group_admin/([^/]+)/.*|", "$1", $share['path']);
					$sharepath = preg_replace('|^/*user_group_admin/[^/]+/*|', '', $share['path']);
				}
				else{
					$sharepath = preg_replace('|^/*files/|', '', $share['path']);
				}
				$sharename = preg_replace('|^.*/|', '', $sharepath);
				$filepath = preg_replace('|^'.$share['uid_owner'].'/'.$sharename.'|', '', $path);
				OC_Log::write('chooser','Checking share '.$path.':'.$share['uid_owner'].'/'.$sharename.'/'.
						':'.$sharepath.':'.$filepath, OC_Log::WARN);
				if($path==$share['uid_owner'].'/'.$sharename ||
						strpos($path, $share['uid_owner'].'/'.$sharename.'/')===0){
							$info = \OCA\FilesSharding\Lib::getFileInfo($sharepath.$filepath, $share['uid_owner'],
							/*$share['item_source']*//*Nope - don't use the ID of the shared folder*/'', '',
									$this->auth_user, $group);
					$server = \OCA\FilesSharding\Lib::getServerForUser($share['uid_owner'], false);
					$master = \OCA\FilesSharding\Lib::getMasterURL();
					$path = implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));
					// This hack is to avoid that e.g. cyberduck shows the directory itself in the list of subdirectories.
					// Which happens when listing e.g. /sharingin/test/ and then being redirected to
					// /sharingout/test/.
					// On the redirected end the @@ is stripped off and sharingout replaced with sharingin.
					$redirect = rtrim((empty($server)?$master:$server), '/');
					$redirect = $redirect.(empty($_SERVER['HTTP_USER_AGENT']) ||
							stripos($_SERVER['HTTP_USER_AGENT'], "cyberduck")===false?
							'/sharingout/'.$path:
							'/sharingout/@@/'.$path);
					OC_Log::write('chooser','Redirecting sharingin target '.$path.' to '.$share['uid_owner'].'-->'.$redirect, OC_Log::WARN);
					\OC_Response::redirect($redirect);
					exit();
				}
			}
			if($found){
				return new \OC_Connector_Sabre_Sharingin_Directory($path);
			}
			throw new \Sabre\DAV\Exception\NotFound('File with name ' . $path . ' could not be located');
		}
		
		elseif(isset($this->sharingOut) && $this->sharingOut){
			// Deal with sharingout/some.user@inst.dk/
			$shareeRoot = false;
			if(preg_match('|^[^/]+$|', $path)){
				$shareeRoot = true;
			}
			//else
				// Now deal with sharingout/some.user@inst.dk/some_share
			if(empty($this->auth_user)){
				OC_Log::write('chooser','EMPTY USER '.serialize($_SERVER), OC_Log::WARN);
				return false;
			}
			\OC_Util::teardownFS();
			\OC_User::setUserId($this->auth_user);
			\OC_Util::setupFS($this->auth_user);
			OC_Log::write('chooser','Creating sharingout sharee dir '.$this->auth_user.':'.$path, OC_Log::WARN);
			$shares = \OCA\Files\Share_files_sharding\Api::getFilesSharedWithMe();
			$found = false;
			foreach($shares->getData() as $share) {
				OC_Log::write('chooser','checking sharee '.$share['uid_owner'].' -->  '.$share['path'], OC_Log::WARN);
				if(strpos($path, $share['uid_owner'].'/')!==0 && $path!=$share['uid_owner']){
					continue;
				}
				$found = true;
				if($shareeRoot){
					break;
				}
				$group = '';
				if(!empty($share['path']) && preg_match("|^/*user_group_admin/|", $share['path'])){
					$group = preg_replace("|^/*user_group_admin/([^/]+)/.*|", "$1", $share['path']);
					$sharepath = preg_replace('|^/*user_group_admin/[^/]+/*|', '', $share['path']);
				}
				else{
					$sharepath = preg_replace('|^/*files/|', '', $share['path']);
				}
				$sharename = preg_replace('|^.*/|', '', $sharepath);
				$filepath = preg_replace('|^'.$share['uid_owner'].'/'.$sharename.'|', '', $path);
				OC_Log::write('chooser','checking path '.$filepath.'<-->'.$sharepath.' : '.$group, OC_Log::WARN);
				if($path==$share['uid_owner'].'/'.$sharename ||
						strpos($path, $share['uid_owner'].'/'.$sharename.'/')===0){
					$info = \OCA\FilesSharding\Lib::getFileInfo($sharepath.$filepath, $share['uid_owner'],
							/*$share['item_source']*//*Nope - don't use the ID of the shared folder*/'',
							'', $this->auth_user, $group);
					\OC_Util::teardownFS();
					\OC_User::setUserId($share['uid_owner']);
					\OC_Util::setupFS($share['uid_owner']);
					\OC\Files\Filesystem::init($share['uid_owner'],
							!empty($group)?'/'.$share['uid_owner'].'/user_group_admin/'.$group:
															'/'.$share['uid_owner'].'/files');
					$this->fileView = \OC\Files\Filesystem::getView();
					OC_Log::write('chooser','Using view '.$share['path'].':'.$path.':'.$filepath.':'.
							$info->getType().':'.$info->getPermissions(), OC_Log::WARN);
					break;
				}
			}
			if($found && $shareeRoot){
				OC_Log::write('chooser','Returning sharingout sharee dir '.$path, OC_Log::WARN);
				return new \OC_Connector_Sabre_Sharingout_Directory($path);
			}
			if(!$found && empty($info)){
				throw new \Sabre\DAV\Exception\NotFound('File with name ' . $path . ' could not be located. '.$found);
			}
		}
		
		elseif(isset($this->favorites) && $this->favorites){
			OC_Log::write('chooser','ERROR: we should not get here...'.$path, OC_Log::ERROR);
		}
		
		if(empty($info)){
			if (pathinfo($path, PATHINFO_EXTENSION) === 'part') {
				// read from storage
				$absPath = $this->fileView->getAbsolutePath($filepath);
				list($storage, $internalPath) = Filesystem::resolvePath('/' . $absPath);
				if ($storage) {
					$scanner = $storage->getScanner($internalPath);
					// get data directly
					$info = $scanner->getData($internalPath);
				}
			}
			else {
				// read from cache
				$info = $this->fileView->getFileInfo($filepath);
			}
		}

		if (!$info) {
			throw new \Sabre\DAV\Exception\NotFound('File with name ' . $filepath . ' could not be located');
		}
		
		OC_Log::write('chooser','Returning Sabre '.$info->getType().': '.$info->getPath(), OC_Log::INFO);
		
		if ($info->getType() === 'dir') {
			$node = new \OC_Connector_Sabre_Directory($this->fileView, $info);
		} else {
			$node = new \OC_Connector_Sabre_File($this->fileView, $info);
		}
		
		$this->cache[$path] = $node;
		return $node;

	}

}