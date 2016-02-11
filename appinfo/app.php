<?php

require_once('apps/chooser/lib/lib_chooser.php');

OCP\App::registerPersonal('chooser', 'personalsettings');

if(!empty($_SERVER['PHP_AUTH_USER'])){
	OC_Log::write('chooser','user_id '.$_SERVER['PHP_AUTH_USER'],OC_Log::DEBUG);
}
else{
	$user_id = OC_Chooser::checkIP();
	#$user_id = "fror@dtu.dk";
	
	OC_Log::write('chooser','user_id '.$user_id,OC_Log::DEBUG);
	
	if($user_id != '' && OC_User::userExists($user_id)){
	   $_SESSION['user_id'] = $user_id;
	   \OC_Util::setupFS();
	}
}
