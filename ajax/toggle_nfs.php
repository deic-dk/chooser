<?php

OCP\JSON::checkAppEnabled('chooser');
OCP\JSON::checkLoggedIn();

require_once('apps/chooser/lib/lib_chooser.php');

$old_value = OC_Chooser::getStorageEnabled();

$new_value = $old_value == 'no' ? 'yes' : 'no';

$ret = [];

if(!OC_Chooser::setStorageEnabled($new_value)){
	$ret['error'] = "Failed enabling /storage";
}

$ret['msg'] = "Storage NFS: ".$new_value;

OCP\JSON::encodedPrint($ret);