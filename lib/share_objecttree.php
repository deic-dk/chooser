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
	public $group = false;
	private $shareStrings = array();
	private $shareETags = array();
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
		\OC_Log::write('chooser','Getting node for '.$path.':'.$_SERVER['REQUEST_URI'].
				':'.$this->sharingIn.':'.$this->sharingOut, \OC_Log::INFO);
		
		if($this->allowUpload==false &&
		(strtolower($_SERVER['REQUEST_METHOD'])=='mkcol' || strtolower($_SERVER['REQUEST_METHOD'])=='put' ||
				strtolower($_SERVER['REQUEST_METHOD'])=='move' || strtolower($_SERVER['REQUEST_METHOD'])=='copy' ||
				strtolower($_SERVER['REQUEST_METHOD'])=='delete' ||
		strtolower($_SERVER['REQUEST_METHOD'])=='proppatch')){
			throw new \Sabre\DAV\Exception\Forbidden($_SERVER['REQUEST_METHOD'].' not allowed. '.$this->allowUpload);
		}
	
		$this->fixPath($path);

		$path = trim($path, '/');
		if(empty($this->sharingIn) && isset($this->cache[$path])){
			OC_Log::write('chooser','Returning cache '.$path, OC_Log::INFO);
			return $this->cache[$path];
		}

		// Is it the root node?
		/*if(!strlen($path)){
			return $this->rootNode;
		}*/
		$filepath = $path;
		if(isset($this->sharingIn) && $this->sharingIn){
			// First deal with sharingin/some.user@inst.dk/
			$shareeRoot = false;
			if(preg_match('|^[^/]+$|', $path)){
				$shareeRoot = true;
				OC_Log::write('chooser','Creating sharingin sharee dir '.$path, OC_Log::WARN);
			}
			//else
			// Now deal with sharingin/some.user@inst.dk/some_share
			$shares = \OCA\Files\Share_files_sharding\Api::getFilesSharedWithMe();
			$found = false;
			$setEtags = empty($this->shareStrings);
			OC_Log::write('chooser','Setting etags? '.$setEtags.' : '.empty($this->shareStrings).' : '.
					serialize($this->shareStrings).'-->'.serialize($this->shareETags), OC_Log::DEBUG);
			foreach($shares->getData() as $share) {
				if(!empty($path) && strpos($path, $share['uid_owner'].'/')!==0 && $path!=$share['uid_owner']){
					continue;
				}
				if($shareeRoot){
					$found = true;
					if(!$setEtags){
						OC_Log::write('chooser','Not Setting etags of '.$path.'->'.$share['uid_owner'].'->'.
								$this->shareStrings[$share['uid_owner']], OC_Log::INFO);
						continue;
						// Continue to generate etag for sharingin/
					}
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
				$filepath = substr($path, 0, strlen($share['uid_owner'].'/'.$sharename))==
					$share['uid_owner'].'/'.$sharename?
					preg_replace('|^'.$share['uid_owner'].'/'.$sharename.'|', '', $path):'';
				OC_Log::write('chooser','Checking share '.$path.':'.$share['uid_owner'].'/'.$sharename.'/'.
						':'.$sharepath.':'.$filepath.':'.$_SERVER['REQUEST_URI'].':'.$this->sharingIn, OC_Log::INFO);
				if($path==$share['uid_owner'] /*this is to generate etag from shares*/ ||
						$path==$share['uid_owner'].'/'.$sharename ||
						strpos($path, $share['uid_owner'].'/'.$sharename.'/')===0 || empty($path)){
					$info = \OCA\FilesSharding\Lib::getFileInfo($sharepath.'/'.$filepath, $share['uid_owner'],
							/*$share['item_source']*//*Nope - don't use the ID of the shared folder*/'', '',
							$this->auth_user, $group);
					$server = \OCA\FilesSharding\Lib::getServerForUser($share['uid_owner'], false);
					//$serverInternal = \OCA\FilesSharding\Lib::getServerForUser($share['uid_owner'], true);
					$master = \OCA\FilesSharding\Lib::getMasterURL();
					//$masterInternal = \OCA\FilesSharding\Lib::getMasterInternalURL();
					$path = implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));
					// This hack is to avoid that e.g. cyberduck shows the directory itself in the list of subdirectories.
					// Which happens when listing e.g. /sharingin/test/ and then being redirected to
					// /sharingout/test/.
					// On the redirected end the @@ is stripped off and sharingout replaced with sharingin.
					$redirect = rtrim((empty($server)?$master:$server), '/');
					//$etagUrl = rtrim((empty($serverInternal)?$masterInternal:$serverInternal), '/').'/sharingout/'.$path;
					$redirect = $redirect.(empty($_SERVER['HTTP_USER_AGENT']) ||
							stripos($_SERVER['HTTP_USER_AGENT'], "cyberduck")===false?
							'/sharingout/'.$path:
							'/sharingout/@@/'.$path);
					// httpMkcol and httpPut call getNodeForPath on parent
					if((strtolower($_SERVER['REQUEST_METHOD'])=='mkcol' ||
								strtolower($_SERVER['REQUEST_METHOD'])=='put' ||
								strtolower($_SERVER['REQUEST_METHOD'])=='move' ||
								strtolower($_SERVER['REQUEST_METHOD'])=='copy') &&
						basename($_SERVER['REQUEST_URI'])!=basename($path) &&
						basename(dirname($_SERVER['REQUEST_URI']))==basename($path)){
						$redirect = $redirect.'/'.basename($_SERVER['REQUEST_URI']);
					}
					/*if(!strlen($path)){
						$etagUrl = $etagUrl.rawurlencode($share['uid_owner']).'/'.rawurlencode($sharename);
					}*/
					if($shareeRoot || !strlen($path)){
						// Create getetag for sharingin/some.user@inst.dk/ by summing
						// those of shared folders
						/*OC_Log::write('chooser','Getting share etag for '.$path.' from '.$etagUrl, OC_Log::WARN);
						$shareEtag= \OCA\FilesSharding\Lib::propfind($etagUrl, 'd:getetag', $this->auth_user);*/
						$shareEtag = $info['etag'];
						OC_Log::write('chooser','Setting etag for share '.$info['path'].' : '.
								$sharename.' : '.$sharepath.' : '.$filepath.' : '.$path.' : '.$shareEtag.
								' : '.(strpos($path, $share['uid_owner'].'/'.$sharename)===0), OC_Log::INFO);
						if(empty($shareEtag)){
							OC_Log::write('chooser','Error: getetag not found for '.$info['path'], OC_Log::ERROR);
						}
						elseif($setEtags){
							$this->shareStrings[$share['uid_owner']] = (empty($this->shareStrings[$share['uid_owner']])?
									"":$this->shareStrings[$share['uid_owner']]).$shareEtag;
							$this->shareStrings[$share['uid_owner'].'/'.$sharename] = $shareEtag;
						}
					}
					else{
						$testEx = new \Exception();
						OC_Log::write('chooser','Redirecting sharingin target '.$_SERVER['REQUEST_URI'].' : '.$path.' to '.
								$share['uid_owner'].'-->'.$redirect.' : '.$testEx->getTraceAsString(), OC_Log::WARN);
						\OC_Response::redirect($redirect);
						exit();
					}
				}
			}
			if(!strlen($path)){
				$ret = $this->rootNode;
				$etag = "";
				foreach($this->shareStrings as $mypath=>$str){
					if(preg_match('|^[^/]+$|', $mypath)){
						$etag = $etag.$str;
					}
				}
				$etag = substr(md5($etag), 0, 13);
				OC_Log::write('chooser','Setting etag of /sharingin: '.$path.'->'.$etag, OC_Log::INFO);
				$ret->setETag('"'.$etag.'"');
			}
			elseif($found){
				$ret = new \OC_Connector_Sabre_Sharingin_Directory($path);
				if($setEtags){
					$etag = empty($path)?'':substr(md5($this->shareStrings[$path]), 0, 13);
					$ret->setETag('"'.$etag.'"');
					//$ret->setETag('"'.$this->shareStrings[$path].'"');
					OC_Log::write('chooser','Setting etag '.$etag.' of '.$path, OC_Log::INFO);
				}
			}
			else{
				OC_Log::write('chooser','NOT Setting etag '.$etag.' of '.$path, OC_Log::INFO);
			}
			if(!empty($ret)){
				if($setEtags){
					foreach($this->shareStrings as $mypath=>$str){
						if(!empty($str)){
							$this->shareETags[$mypath] = substr(md5($str), 0, 13);
						}
					}
				}
				OC_Log::write('chooser','Setting etags '.$setEtags.' : '.serialize($this->shareStrings).'-->'.
						serialize($this->shareETags), OC_Log::INFO);
				$ret->setETags($this->shareETags);
				return $ret;
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
					if(empty($info)){
						throw new \Sabre\DAV\Exception\NotFound('File with name ' . $sharepath.$filepath . ' could not be located.');
					}
					\OC_Util::teardownFS();
					\OC_User::setUserId($share['uid_owner']);
					\OC_Util::setupFS($share['uid_owner']);
					$root = !empty($group)?'/'.$share['uid_owner'].'/user_group_admin/'.$group.'/'.$sharepath:
					'/'.$share['uid_owner'].'/files/'.$sharepath;
					\OC\Files\Filesystem::init($share['uid_owner'], $root);
					$this->fileView = \OC\Files\Filesystem::getView();
					$this->fileView->chroot($root);
					//$_SERVER['REQUEST_URI'] = preg_replace("|^/sharingout/".$share['uid_owner'].'/'.$sharename."|", "", $_SERVER['REQUEST_URI']);
					OC_Log::write('chooser','Using view '.$sharepath.':'.$filepath.':'.$_SERVER['REQUEST_URI'].':'.
							$info->getType().':'.$info->getPermissions().':'.$found.':'.$shareeRoot.':'.$this->fileView->getRoot(), OC_Log::WARN);
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
			//$e = new \Exception();
			//OC_Log::write('chooser','ERROR: we should not get here...'.$e->getTraceAsString(), OC_Log::ERROR);
			return $this->rootNode;
		}
		
		if(empty($info)){
			if (pathinfo($path, PATHINFO_EXTENSION) === 'part') {
				// read from storage
				$absPath = $this->fileView->getAbsolutePath($filepath);
				list($storage, $internalPath) = \OC\Files\Filesystem::resolvePath('/' . $absPath);
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

		if (empty($info)) {
			throw new \Sabre\DAV\Exception\NotFound('File with name ' . $filepath . ' could not be located');
		}
		
		OC_Log::write('chooser','Returning Sabre '.$info->getType().': '.$path.' : '.$info->getPath(), OC_Log::INFO);
		
		if ($info->getType() === 'dir') {
			$node = new \OC_Connector_Sabre_Directory($this->fileView, $info);
		} else {
			$node = new \OC_Connector_Sabre_File($this->fileView, $info);
		}
		
		$this->cache[$path] = $node;
		return $node;

	}
	
	// This is here to fix an ownCloud race condition bug:
	
	/*
	 Server.php: httpMove() : tree->move()
	
	objecttree.php: move() : fileView->rename()
	
	view.php: storage->rename()
	
	view.php: rename() : updater->rename()
	
	updater.php: rename() : $cache->move()
	
	BUT:
	
	BEFORE $cache->move(), something triggers
	
	WHICH somehow triggers watcher.php: checkUpdate($source)
	WHICH calls scanner->scan()
	WHICH calls scanner.php: scanChildren(), removeFromCache()
	
	Well, actually it's triggered by another webdav call (PROPFIND).
	So it's an ownCloud bug.
	
	Notice that scanner.php has been hacked to check the flagging.
	
	*/
	
	public function move($sourcePath, $destinationPath) {
		$user_id = \OCP\User::getUser();
		if(isset($this->sharingOut) && $this->sharingOut){
			// Strip off /[owner]/[sharename]. The root of $this->fileView has been set to [sharepath].
			$sourcePath = preg_replace('|^[^/]+/[^/]+/|', '/', $sourcePath);
			$destinationPath = preg_replace('|^[^/]+/[^/]+/|', '/', $destinationPath);
		}
		\OCP\Util::writeLog('Chooser', 'Checking source being moved: '.$sourcePath.' --> '.
				$destinationPath.' :: '.$this->fileView->getRoot().' :: '.$_SERVER['REQUEST_URI'].' --> '.
				(empty($_SERVER['HTTP_DESTINATION'])?'':$_SERVER['HTTP_DESTINATION']), \OCP\Util::WARN);
		$info = $this->fileView->getFileInfo($sourcePath);
		if(!empty($user_id) && !empty($info)){
			\OCP\Util::writeLog('Chooser', 'Flagging source being moved: '.$info->getInternalPath().' --> '.
					$destinationPath, \OCP\Util::WARN);
			apc_store(\OC_Chooser::$MOVING_CACHE_PREFIX.$user_id.':'.$info->getInternalPath(), '1',
					10*60 /*give 10 minutes to move*/);
		}
		if(isset($this->sharingOut) && $this->sharingOut){
			// We bypass the mountmanager stuff - permissions have been checked
			if(\OC\Files\Filesystem::file_exists($destinationPath)){
				throw new \Sabre\DAV\Exception\Forbidden('File exists');
			}
			$renameOkay = $this->fileView->rename($sourcePath, $destinationPath);
			if(!$renameOkay){
			 	throw new \Sabre\DAV\Exception\Forbidden('');
			}
			$query = \OC_DB::prepare('UPDATE `*PREFIX*properties` SET `propertypath` = ?'.
				' WHERE `userid` = ? AND `propertypath` = ?');
			$query->execute(array(\OC\Files\Filesystem::normalizePath($destinationPath), \OC_User::getUser(),
			\OC\Files\Filesystem::normalizePath($sourcePath)));
			list($sourceDir,) = \Sabre\DAV\URLUtil::splitPath($sourcePath);
			list($destinationDir,) = \Sabre\DAV\URLUtil::splitPath($destinationPath);
			$this->markDirty($sourceDir);
			$this->markDirty($destinationDir);
		}
		else{
			\OCP\Util::writeLog('Chooser', 'Moving: '.$sourcePath.' --> '.$destinationPath, \OCP\Util::WARN);
			return parent::move($sourcePath, $destinationPath);
		}
	}
	
	public function copy($source, $destination) {
		if(isset($this->sharingOut) && $this->sharingOut){
			// Strip off /[owner]/[sharename]. The root of $this->fileView has been set to [sharepath].
			$source = preg_replace('|^[^/]+/[^/]+/|', '/', $source);
			$destination = preg_replace('|^[^/]+/[^/]+/|', '/', $destination);
			try{
				if ($this->fileView->is_file($source)) {
					$this->fileView->copy($source, $destination);
				}
				else{
					$this->fileView->mkdir($destination);
					$dh = $this->fileView->opendir($source);
					if (is_resource($dh)) {
						while (($subNode = readdir($dh)) !== false) {
							if ($subNode == '.' || $subNode == '..') continue;
							$this->copy($source . '/' . $subNode, $destination . '/' . $subNode);
						}
					}
				}
			}
			catch (\OCP\Files\StorageNotAvailableException $e) {
				throw new \Sabre\DAV\Exception\ServiceUnavailable($e->getMessage());
			}
			
			list($destinationDir,) = \Sabre\DAV\URLUtil::splitPath($destination);
			$this->markDirty($destinationDir);
		}
		else{
			return parent::copy($source, $destination);
		}
	}

}