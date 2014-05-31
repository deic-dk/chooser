<?php

require_once('apps/chooser/lib/lib_chooser.php');

OCP\App::registerPersonal('chooser', 'settings');

$user_id = OC_Chooser::checkIP();
#$user_id = "fror@dtu.dk";

OC_Log::write('chooser','user_id '.$user_id,OC_Log::WARN);

if($user_id != '' && OC_User::userExists($user_id)){
   $_SESSION['user_id'] = $user_id;
   \OC_Util::setupFS();
}

