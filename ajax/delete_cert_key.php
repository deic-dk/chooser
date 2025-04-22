<?php

OCP\JSON::checkAppEnabled('chooser');
OCP\JSON::checkLoggedIn();

$user_id = OCP\USER::getUser();

OC_Log::write('files_sharding',"Deleting cert/key for ".$user_id, OC_Log::WARN);

$ret = [];

if(OC_Chooser::deleteSDCertKey($user_id)){
	$ret['message'] = "Removed cert/key";
}
else{
	$ret['error'] = "Failed deleting certificate/key for user ".$user_id;
}

OCP\JSON::encodedPrint($ret);
