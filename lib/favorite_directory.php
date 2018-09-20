<?php

require_once 'chooser/lib/oc_remote_view.php';

class OC_Connector_Sabre_Favorite_Directory extends OC_Connector_Sabre_Directory
	implements \Sabre\DAV\ICollection, \Sabre\DAV\IQuota {

	protected $href;
	
	public function __construct($view, $info) {
		$this->fileView = $view;
		$this->path = $this->fileView->getRelativePath($info->getPath());
		$this->info = $info;
		$data = $info->getData();
		$this->href = empty($data['href'])?'':$data['href'];
	}
	
	public function getHref() {
		return $this->href;
	}
	
	public function getOcFileId() {
		return $this->info->getId();
	}

}
