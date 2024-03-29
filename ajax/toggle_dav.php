<?php

OCP\JSON::checkAppEnabled('chooser');
OCP\JSON::checkLoggedIn();

require_once('apps/chooser/lib/lib_chooser.php');

$old_value = OC_Chooser::getInternalDavEnabled();

$new_value = $old_value == 'no' ? 'yes' : 'no';

$ret = [];

if(!OC_Chooser::setInternalDavEnabled($new_value)){
	$ret['error'] = "Failed enabling internal DAV";
}

OCP\JSON::encodedPrint($ret);