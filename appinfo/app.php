<?php

require_once('apps/chooser/lib/lib_chooser.php');

OCP\App::registerPersonal('chooser', 'personalsettings');

if(!empty($_SERVER['PHP_AUTH_USER'])){
	OC_Log::write('chooser','user_id '.$_SERVER['PHP_AUTH_USER'],OC_Log::DEBUG);
}
elseif(strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/index.php/login")===0){
	OC_Log::write('chooser','iOS login', OC_Log::WARN);
	// No idea why this is necessary...
	require_once('apps/chooser/login.php');
}
else{
	$user_id = OC_Chooser::checkIP();
	#$user_id = "fror@dtu.dk";
	OC_Log::write('chooser','user_id '.$user_id ,OC_Log::WARN);
	if(!empty($user_id) && OC_User::userExists($user_id)){
	   $_SESSION['user_id'] = $user_id;
	   \OC_Util::setupFS();
	}
}

require_once('apps/chooser/appinfo/apache_note_user.php');
