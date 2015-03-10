<?php

OCP\JSON::checkAppEnabled('chooser');
OCP\User::checkLoggedIn();

OCP\Util::addscript('chooser', 'personalsettings');

$tmpl = new OCP\Template( 'chooser', 'personalsettings');

return $tmpl->fetchPage();
