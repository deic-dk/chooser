<?php

require_once('apps/chooser/lib/lib_chooser.php');

OCP\JSON::checkAppEnabled('chooser');
OCP\User::checkLoggedIn();

OCP\Util::addscript('chooser', 'personalsettings');

$tmpl = new OCP\Template( 'chooser', 'personalsettings');

$tmpl->assign('is_enabled', OC_Chooser::getEnabled());
$tmpl->assign('ssl_cert_dn', OC_Chooser::getCertSubject(OCP\USER::getUser()));

return $tmpl->fetchPage();
