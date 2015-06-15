<?php

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