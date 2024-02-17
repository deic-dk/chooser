<?php

OCP\JSON::checkAppEnabled('chooser');
OCP\JSON::checkLoggedIn();

$days = $_GET['days'];
$user_id = OCP\USER::getUser();

$ret = [];

$ret['msg'] = "";

if($res=OC_Chooser::generateUserCert($days, $user_id)){
	$ret['message'] .= "Generated certificate with DN ".$res['dn'];
	$ret['dn'] = $res['dn'];
	$ret['expires'] = $res['expires'];
}
else{
	$ret['error'] = "Failed generating certificate for user ".$user_id;
}


OCP\JSON::encodedPrint($ret);
