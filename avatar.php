<?php 

\OC_Log::write('chooser', 'Returning image for '.$_REQUEST['user'], \OC_Log::WARN);

// Uncomment to use real avatars on blog instead of letters
/*if((empty($_REQUEST['use_letter']) || $_REQUEST['use_letter']=='no' || $_REQUEST['use_letter']=='false') &&
		!empty($_REQUEST['user']) && \OC_User::userExists($_REQUEST['user'])){
	$user = $_REQUEST['user'];
	$masterUrl = OCA\FilesSharding\Lib::getMasterURL();
	$serverUrl = OCA\FilesSharding\Lib::getServerForUser($user);
	if(!OCA\FilesSharding\Lib::onServerForUser($user)){
		if(!empty($_SERVER['HTTP_DESTINATION'])){
			$destination = preg_replace('|^'.$masterUrl.'|', $serverUrl, $_SERVER['HTTP_DESTINATION']);
			header("Destination: " . $destination);
		}
		header("HTTP/1.1 301 Moved Permanently");
		header("Location: " . $serverUrl . $_SERVER['REQUEST_URI']);
		header("User: " . $user);
		exit;
	}
}*/

getAvatar($_REQUEST['user'], $_REQUEST['size']);

exit;

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