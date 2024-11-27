<?php

OCP\JSON::checkAppEnabled('files_sharding');
OCP\JSON::checkAppEnabled('chooser');
OCP\JSON::checkLoggedIn();

$user_id = empty($_GET['user_id'])?\OCP\USER::getUser():$_GET['user_id'];
$device_name = $_GET['device_name'];

OC_Log::write('files_sharding',"Delete device token for device ".$device_name." for ".$user_id, OC_Log::WARN);

$ret = [];
if(!empty($device_name)){
	\OC_Chooser::deleteDeviceToken($user_id, $device_name);
	ret['msg'] = "Deleted token for device ".$device_name." for  ".$user_id;
}
else{
	$ret['error'] = "Failed deleting token for device ".$device_name." for  ".$user_id;
}

OCP\JSON::encodedPrint($ret);
