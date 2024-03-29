<?php

OCP\JSON::checkAppEnabled('chooser');
OCP\JSON::checkLoggedIn();

$dn = $_POST['dn'];
$user_id = OCP\USER::getUser();

OC_Log::write('files_sharding',"Setting DN: ".$user_id.":".$dn, OC_Log::WARN);

$ret = [];


if($dn===""){
	if(OC_Chooser::removeCert($user_id, $dn)){
		$ret['message'] = "Cleared all DNs. You can no longer authenticate with an SSL certificate.";
	}
	else{
		$ret['error'] = "Failed clearing DN ".$dn." for user ".$user_id;
	}
}

if(OC_Chooser::addCert($user_id, $dn)){
	$ret['message'] = "Added DN ".$dn;
}
else{
	$ret['error'] = "Failed setting DN ".$dn." for user ".$user_id;
}

OCP\JSON::encodedPrint($ret);
