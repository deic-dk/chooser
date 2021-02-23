<?php

require_once('apps/chooser/lib/lib_chooser.php');
require_once('apps/chooser/lib/device_auth.php');

// This is to add device auth to the /ocs/ stuff called by the sync clients.
if(strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/ocs/")===0){
	OCP\Util::connectHook('OC', 'initSession', 'Sabre\DAV\Auth\Backend\Device', 'login');
}

OCP\App::registerPersonal('chooser', 'personalsettings');

if(!empty($_SERVER['PHP_AUTH_USER'])){
	OC_Log::write('chooser','user_id '.$_SERVER['PHP_AUTH_USER'],OC_Log::DEBUG);
}
elseif(!empty($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/index.php/login")===0){
	OC_Log::write('chooser','iOS login', OC_Log::WARN);
	require_once('apps/chooser/login.php');
}
else{
	$user_id = OC_Chooser::checkIP();
	#$user_id = "fror@dtu.dk";
	OC_Log::write('chooser','user_id '.$user_id ,OC_Log::INFO);
	if(!empty($user_id) && OC_User::userExists($user_id)){
	   $_SESSION['user_id'] = $user_id;
	   \OC_Util::setupFS();
	}
}

\OC_User::useBackend('database');
require_once('apps/chooser/appinfo/apache_note_user.php');
