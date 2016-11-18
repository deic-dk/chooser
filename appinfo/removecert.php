<?php

OCP\JSON::checkAppEnabled('chooser');
require_once('chooser/lib/lib_chooser.php');
require_once('chooser/lib/ip_auth.php');
require_once('chooser/lib/nbf_auth.php');

$subject = isset($_POST['subject']) ? $_POST['subject'] : '';

if(empty($subject)){
	header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
	exit();
}

$ok = false;

if(!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])){
	$authBackendNBF = new OC_Connector_Sabre_Auth_NBF();
	$ok = $authBackendNBF->checkUserPass($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
}

if(!$ok){
	$authBackendIP = new Sabre\DAV\Auth\Backend\IP();
	$ok = $authBackendIP->checkUserPass($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
}

if(!$ok){
	header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
	exit();
}

OCP\JSON::checkLoggedIn();
$user = \OCP\USER::getUser();

require_once('apps/chooser/appinfo/apache_note_user.php');

if(OC_Chooser::removeCert()){
	OCP\JSON::success(array('data' =>
		array('message'=>'Successfully revoked access to X.509 certificate/key with subject '.
		$subject.' for user '.$user)));
}
else{
	OCP\JSON::error(array('data' =>
		array('message'=>'Failed revoking access to X.509 subject '.$subject.' for user '.$user)));
}

