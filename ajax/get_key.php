<?php

OCP\JSON::checkAppEnabled('chooser');
OCP\JSON::checkLoggedIn();

$user_id = OCP\USER::getUser();

header("Content-Type: text/plain");
header("Content-Disposition: attachment; filename=userkey.pem");

echo(OC_Chooser::getSDKey($user_id));

