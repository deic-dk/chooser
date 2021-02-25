<?php 

\OC_Log::write('saml', 'Returning image for '.$_REQUEST['user'], \OC_Log::WARN);

getAvatar($_REQUEST['user'], $_REQUEST['size']);

function getAvatar($user, $size) {
	//\OC_JSON::checkLoggedIn();
	//\OC_JSON::callCheck();
	//\OC::$server->getSession()->close();
	
	if ($size > 2048) {
		$size = 2048;
	}
	// Undefined size
	elseif ($size === 0) {
		$size = 64;
	}
	
	$avatar = new \OC_Avatar($user);
	$image = $avatar->get($size);
	
	\OC_Response::disableCaching();
	\OC_Response::setLastModifiedHeader(time());
	if ($image instanceof \OC_Image) {
		\OC_Response::setETagHeader(crc32($image->data()));
		$image->show('image/png');
	} else {
		$img = imagecreate($size, $size); // 250
		$hash = md5('color' . $user); // modify 'color' to get a different palette
		imagecolorallocate($img, hexdec(substr($hash, 0, 2)),
				hexdec(substr($hash, 2, 2)), hexdec(substr($hash, 4, 2)));
		$textcolor = imagecolorallocate($img, 255, 255, 255);
		$txt = strtoupper(substr($user, 0, 1));
		$fontfile = \OC::$server->getRootFolder()->getFullPath("/")."/apps/chooser/FreeMonoBold.ttf";
		//imagettftext($img, 100, 0, 85, 170, $textcolor , $fontfile, $txt);
		imagettftext($img, 100*$size/200, 0, 76*$size/250, 170*$size/250, $textcolor , $fontfile, $txt);
		imagepng($img);
	}
}

?>