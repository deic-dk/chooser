<?php

require_once 'chooser/lib/sharingin_directory.php';
require_once 'chooser/lib/sharingout_directory.php';

class Share_ObjectTree extends \OC\Connector\Sabre\ObjectTree {

	public $allowUpload = true;
	public $auth_token = null;
	public $auth_path = null;
	
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
	}
	
	public function sharingInInit() {
		OC_Log::write('chooser','Creating sharingin root dir', OC_Log::WARN);
		$this->rootNode = new \OC_Connector_Sabre_Sharingin_Directory();
	}
	
	public function sharingOutInit() {
		OC_Log::write('chooser','Creating sharingout root dir', OC_Log::WARN);
		$this->rootNode = new \OC_Connector_Sabre_Sharingout_Directory();
	}
	
	public function getNodeForPath($path) {

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
			$user = \OC_User::getUser();
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
				}
				if(basename($share['path'])==basename($path)){
					$info = \OCA\FilesSharding\Lib::getFileInfo($share['path'], $share['uid_owner'], $share['item_source'], '',
							$user, $group);
					$server = \OCA\FilesSharding\Lib::getServerForUser($share['uid_owner'], false);
					$master = \OCA\FilesSharding\Lib::getMasterURL();
					$redirect = rtrim((empty($server)?$master:$server), '/').'/sharingout/'.ltrim($path, '/');
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
			// Deal with sharingin/some.user@inst.dk/
			$shareeRoot = false;
			if(preg_match('|^[^/]+$|', $path)){
				$shareeRoot = true;
			}
			//else
				// Now deal with haringout/some.user@inst.dk/some_share
			OC_Log::write('chooser','Creating sharingout sharee dir '.$path, OC_Log::WARN);
			$shares = \OCA\Files\Share_files_sharding\Api::getFilesSharedWithMe();
			$user = \OC_User::getUser();
			$found = false;
			foreach($shares->getData() as $share) {
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
				}
				if(basename($share['path'])==basename($path)){
					$info = \OCA\FilesSharding\Lib::getFileInfo($share['path'], $share['uid_owner'], $share['item_source'], '',
							$user, $group);
				}
			}
			if($found){
				return new \OC_Connector_Sabre_Sharingout_Directory($path);
			}
			throw new \Sabre\DAV\Exception\NotFound('File with name ' . $path . ' could not be located');
		}
		
		if (pathinfo($path, PATHINFO_EXTENSION) === 'part') {
			// read from storage
			$absPath = $this->fileView->getAbsolutePath($path);
			list($storage, $internalPath) = Filesystem::resolvePath('/' . $absPath);
			if ($storage) {
				$scanner = $storage->getScanner($internalPath);
				// get data directly
				$info = $scanner->getData($internalPath);
			}
		}
		else {
			// read from cache
			$info = $this->fileView->getFileInfo($path);
		}

		if (!$info) {
			throw new \Sabre\DAV\Exception\NotFound('File with name ' . $path . ' could not be located');
		}

		
		if ($info->getType() === 'dir') {
			$node = new \OC_Connector_Sabre_Directory($this->fileView, $info);
		} else {
			$node = new \OC_Connector_Sabre_File($this->fileView, $info);
		}
		
		$this->cache[$path] = $node;
		return $node;

	}

}