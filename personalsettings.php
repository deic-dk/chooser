<?php

require_once('apps/chooser/lib/lib_chooser.php');

OCP\JSON::checkAppEnabled('chooser');
OCP\User::checkLoggedIn();

OCP\Util::addscript('chooser', 'personalsettings');

$user = OCP\USER::getUser();
$tmpl = new OCP\Template( 'chooser', 'personalsettings');

$tmpl->assign('dav_enabled', OC_Chooser::getInternalDavEnabled());
$tmpl->assign('dav_path', OC_Chooser::getInternalDavDir());
$tmpl->assign('storage_enabled', OC_Chooser::getStorageEnabled());
$tmpl->assign('sd_cert_dn', OC_Chooser::getSDCertSubject($user));
$tmpl->assign('sd_cert_expires', OC_Chooser::getSDCertExpires($user));
$tmpl->assign('ssl_active_dns', OC_Chooser::getActiveDNs($user));

return $tmpl->fetchPage();
