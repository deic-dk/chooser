<?php

OCP\JSON::checkAppEnabled('chooser');
OCP\JSON::checkLoggedIn();

$user_id = OCP\USER::getUser();

header("Content-Type: application/x-pkcs12");
header("Content-Disposition: attachment; filename=usercertkey.p12");

echo(OC_Chooser::getSDPKCS12($user_id));

