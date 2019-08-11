<?php


class OC_Remote_View extends OC\Files\View {
	
	public $root = '';
	
	public function __construct($root = '') {
		$this->root = $root;
	}

	public function getAbsolutePath($path = '/') {
		return '/';
	}

	public function getRelativePath($path) {
		return '/';
	}
	
	public function __toString(){
		return $this->root;
	}

}