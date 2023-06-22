<?php

require_once('apps/chooser/lib/lib_chooser.php');
require_once('apps/chooser/lib/device_auth.php');

// If this is a request from 10.2.0.0/16 or 10.0.0.0/24 we allow http.
OCP\Util::connectHook('OC', 'initSession', 'OC_Chooser', 'checkAllowHttp');

// This is to add device auth to the /ocs/ stuff called by the sync clients.
if(!empty($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/ocs/")===0){
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

//if(\OC_Chooser::getStorageEnabled()=='yes'){ /*Doesn't work. No user yet...*/
	\OCP\App::addNavigationEntry(
		array(
			'appname' => 'files_external',
			'id' => 'storage',
			'order' => 3,
			'href' => OC::$WEBROOT."/index.php/apps/files?dir=%2F&storage=true",
			'name' => 'Storage'
		)
	);
//}

