<?php

OCP\JSON::checkAppEnabled('chooser');
OCP\JSON::checkLoggedIn();

$dn = $_POST['dn'];
$user_id = OCP\USER::getUser();

OC_Log::write('files_sharding',"Removing DN: ".$user_id.":".$dn, OC_Log::WARN);

$ret = [];

if(empty($dn)){
	$ret['error'] = "No DN";
}
else{
	if(OC_Chooser::removeCert($user_id, $dn)){
		$ret['message'] = "Removed DN ".$dn;
		$ret['dn'] = $dn;
	}
	else{
		$ret['error'] = "Failed clearing DN ".$dn." for user ".$user_id;
	}
}

OCP\JSON::encodedPrint($ret);
