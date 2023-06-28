<?php

require_once('apps/chooser/lib/lib_chooser.php');

OCP\JSON::checkAppEnabled('chooser');
OCP\User::checkLoggedIn();

OCP\Util::addscript('chooser', 'personalsettings');

$tmpl = new OCP\Template( 'chooser', 'personalsettings');

$tmpl->assign('dav_enabled', OC_Chooser::getInternalDavEnabled());
$tmpl->assign('dav_path', OC_Chooser::getInternalDavDir());
$tmpl->assign('storage_enabled', OC_Chooser::getStorageEnabled());
$tmpl->assign('ssl_cert_dn', OC_Chooser::getCertSubject(OCP\USER::getUser()));

return $tmpl->fetchPage();
