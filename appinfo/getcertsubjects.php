<?php

OCP\JSON::checkAppEnabled('chooser');
require_once('chooser/lib/lib_chooser.php');
require_once('chooser/lib/ip_auth.php');
require_once('chooser/lib/nbf_auth.php');

$subject = isset($_POST['subject']) ? $_POST['subject'] : '';

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

if($subjects=OC_Chooser::getActiveDNs($user)){
	OCP\JSON::success(array('data'=>array('user'=>$user, 'subjects'=>$subjects)));
}
else{
	OCP\JSON::error(array('message'=>'Failed obtaining certificates of user '.$user));
}

