<?php

require_once 'chooser/lib/oc_remote_view.php';

class OC_Connector_Sabre_Sharingout_Directory extends OC_Connector_Sabre_Node
	implements \Sabre\DAV\ICollection, \Sabre\DAV\IQuota {

		public function __construct($path='/') {
			$this->path = $path;
			$this->info = new \OC\Files\FileInfo('/', -1, '/', array('mtime'=>0));
		}
			
	/**
	 * Creates a new file in the directory
	 *
	 */
	public function createFile($name, $data = null) {
		throw new \Sabre\DAV\Exception\Forbidden('Cannot write to virtual directory!');
	}

	/**
	 * Creates a new subdirectory
	 *
	 * @param string $name
	 * @throws \Sabre\DAV\Exception\Forbidden
	 * @return void
	 */
	public function createDirectory($name) {
		throw new \Sabre\DAV\Exception\Forbidden('Cannot write to virtual directory!');
	}

	/**
	 * Returns a specific child node, referenced by its name
	 *
	 * @param string $name
	 * @param \OCP\Files\FileInfo $info
	 * @throws \Sabre\DAV\Exception\FileNotFound
	 * @return \Sabre\DAV\INode
	 */
	public function getChild($name, $info=null) {
		if(is_null($info) || !$info){
			throw new \Sabre\DAV\Exception\NotFound('Need more info!');
		}
		$this->fileView = new OC_Remote_View();
		if ($info['mimetype'] == 'httpd/unix-directory') {
			\OC_Log::write('chooser','Returning OC_Connector_Sabre_Directory '.$name.':'.$info['fileid'], \OC_Log::WARN);
			$node = new OC_Connector_Sabre_Directory($this->fileView, $info);
		} else {
			$node = new OC_Connector_Sabre_File($this->fileView, $info);
		}
		return $node;
	}

	/**
	 * Returns an array with all the child nodes
	 *
	 * @return \Sabre\DAV\INode[]
	 */
	public function getChildren() {
		$shares = \OCA\Files\Share_files_sharding\Api::getFilesSharedWithMe();
		//$shares = \OCA\Files\Share_files_sharding\Api::getAllShares(array('shared_with_me'=>'false'));
		$nodes = array();
		$owners = array();
		$user = \OC_User::getUser();
		foreach($shares->getData() as $share) {
			if(!OC_User::userExists($share['uid_owner'])){
				continue;
			}
			if($this->path=='/'){
				if(in_array($share['uid_owner'], $owners)){
					continue;
				}
				$owners[] = $share['uid_owner'];
				$info = new \OC\Files\FileInfo($share['uid_owner'], \OC\Files\Filesystem::getStorage('/'.$share['uid_owner'].'/'),
						$share['uid_owner'], array('fileid'=>-1));
				OC_Log::write('chooser','Getting child '.$share['uid_owner'], OC_Log::WARN);
				$node = $this->getChild($share['uid_owner'], $info);
			}
			else{
				if($this->path!=$share['uid_owner']){
					continue;
				}
				\OC_Log::write('chooser','Getting info for '.session_status().', '.serialize($share), \OC_Log::WARN);
				$group = '';
				if(!empty($share['path']) && preg_match("|^/*user_group_admin/|", $share['path'])){
					$group = preg_replace("|^/*user_group_admin/([^/]+)/.*|", "$1", $share['path']);
					$path = $share['path'];
				}
				else{
					$path = $share['path'];
				}
				$info = \OCA\FilesSharding\Lib::getFileInfo($path, $share['uid_owner'], $share['item_source'], '',
						$user, $group);
				\OC_Log::write('chooser','Got info, '.$user.':'.$info['fileid'], \OC_Log::WARN);
				\OC\Files\Filesystem::init($share['uid_owner'],
						!empty($group)?'/'.$share['uid_owner'].'/user_group_admin/'.$group:'/'.$share['uid_owner'].'/files');
				$node = $this->getChild($this->path.'/'.$info->getName(), $info);
			}
			$nodes[] = $node;
		}
		return $nodes;
	}

	/**
	 * Checks if a child exists.
	 *
	 * @param string $name
	 * @return bool
	 */
	public function childExists($name) {

		return true;

	}

	/**
	 * Deletes all files in this directory, and then itself
	 *
	 * @return void
	 * @throws \Sabre\DAV\Exception\Forbidden
	 */
	public function delete() {

		throw new \Sabre\DAV\Exception\Forbidden('Unsharing over webdav not implemented.');

	}

	/**
	 * Returns available diskspace information
	 *
	 * @return array
	 */
	public function getQuotaInfo() {
			return array(0, 0);
	}

	public function getProperties($properties) {
		return array();
	}

}
