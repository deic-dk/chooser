<?php


class OC_Remote_View extends OC\Files\View {
	
	public function __construct($root = '') {
	}

	public function getAbsolutePath($path = '/') {
		return '/';
	}

	public function getRelativePath($path) {
		return '/';
	}

}