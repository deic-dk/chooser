<?php

OCP\JSON::checkAppEnabled('chooser');
OCP\JSON::checkLoggedIn();

$path = $_POST['path'];
$user_id = OCP\USER::getUser();

OC_Log::write('files_sharding',"Setting path: ".$user_id.":".$path, OC_Log::WARN);

$ret = [];

$ret['msg'] = "";

if(OC_Chooser::setInternalDavDir($path)){
	$ret['message'] .= "Saved path";
}
else{
	$ret['error'] = "Failed setting path ".$path." for user ".$user_id;
}

OCP\JSON::encodedPrint($ret);
