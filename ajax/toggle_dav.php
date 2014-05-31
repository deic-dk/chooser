<?php

OCP\JSON::checkAppEnabled('chooser');
OCP\JSON::checkLoggedIn();

require_once('apps/chooser/lib/lib_chooser.php');

$old_value = OC_Chooser::getEnabled();

$new_value = $old_value == 'no' ? 'yes' : 'no';

OC_Chooser::setEnabled($new_value);