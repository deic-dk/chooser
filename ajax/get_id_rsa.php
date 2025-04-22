<?php

OCP\JSON::checkAppEnabled('chooser');
OCP\JSON::checkLoggedIn();

$user_id = OCP\USER::getUser();
$pub = !(empty($_GET['public']) || $_GET['public']=='false');

header("Content-Type: application/x-pem-file");
if($pub){
	header("Content-Disposition: attachment; filename=id_rsa.pub");
}
else{
	header("Content-Disposition: attachment; filename=id_rsa");
}

echo(OC_Chooser::getSDID_RSA($user_id, $pub));

