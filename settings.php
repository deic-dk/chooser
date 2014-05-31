<?php

OCP\JSON::checkAppEnabled('chooser');
OCP\User::checkLoggedIn();

OCP\Util::addscript('chooser', 'settings');

$tmpl = new OCP\Template( 'chooser', 'settings');

return $tmpl->fetchPage();
