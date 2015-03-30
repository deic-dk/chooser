<?php

require_once('apps/chooser/lib/lib_chooser.php');

OCP\JSON::checkAppEnabled('chooser');
OCP\User::checkLoggedIn();

OCP\Util::addscript('chooser', 'personalsettings');

$tmpl = new OCP\Template( 'chooser', 'personalsettings');

$tmpl->assign('is_enabled', OC_Chooser::getEnabled());

return $tmpl->fetchPage();
