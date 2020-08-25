<?php

require_once 'chooser/lib/oc_remote_view.php';
require_once 'chooser/lib/favorite_directory.php';

class OC_Connector_Sabre_Favorites_Directory extends OC_Connector_Sabre_Node
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
		//$this->fileView = \OC\Files\Filesystem::getView();
		if ($info['mimetype'] == 'httpd/unix-directory') {
			\OC_Log::write('chooser','Returning OC_Connector_Sabre_Directory '.$name.':'.$info['fileid'], \OC_Log::WARN);
			$node = new OC_Connector_Sabre_Favorite_Directory($this->fileView, $info);
		} else {
			throw new \Sabre\DAV\Exception\NotImplemented('Favorite files not implemented.');
		}
		return $node;
	}

	/**
	 * Returns an array with all the child nodes
	 *
	 * @return \Sabre\DAV\INode[]
	 */
	public function getChildren(){
		if(!\OCP\App::isEnabled('internal_bookmarks')){
			return array();
		}
		require_once 'apps/internal_bookmarks/lib/intbks.class.php';
		$user = \OC_User::getUser();
		$nodes = array();
		$bookmarks = \OC_IntBks::getAllItemsByUser();
		\OC_Log::write('chooser','Getting children...'.$user.'-->'.serialize($bookmarks), \OC_Log::WARN);
		foreach($bookmarks as $bookmark){
			$target = parse_url('https://localhost'.preg_replace('|^([^&]+)&|', '$1?', $bookmark['bktarget']));
			OC_Log::write('chooser','Target: '.serialize($target), OC_Log::WARN);
			$query = [];
			if(!empty($target['query'])){
				parse_str($target['query'], $query);
			}
			$fileid = '';
			$owner = '';
			$group = '';
			if(!empty($query['owner']) && !empty($query['id'])){
				// For some reason these URLs now seem not to be followed by the iOS client
				// although the links work. Skipping for now.
				// TODO: reenable - and also make PROPPATCH work with group folders and shares
				continue;
				//
				$fileid = $query['id'];
				$owner = $query['owner'];
				if(!empty($query['group'])){
					$group = $query['group'];
				}
				$uri = '/@@/sharingin/'.$query['owner'].$target['path'];
			}
			elseif(!empty($query['group'])){
				// See above
				continue;
				//
				$group = $query['group'];
				$uri = '/@@/group/'.$group.$target['path'];
			}
			else{
				$uri = $target['path'];
			}
			$info = \OCA\FilesSharding\Lib::getFileInfo($target['path'], $owner, $fileid, '', $user, $group);
			$info['href'] = $uri;
			\OC_Log::write('chooser','Got info, '.$group.'-->'.$target['path'].'-->'.$fileid.'-->'.$info['fileid'], \OC_Log::WARN);
			$node = $this->getChild(basename($uri), $info);
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
