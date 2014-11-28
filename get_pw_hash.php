<?php

	OCP\App::checkAppEnabled('user_saml');

	include("lib/session.php");

	$uid = $_POST['id'];
	
	$ret = array();

	$query = OC_DB::prepare( "SELECT `password` FROM `*PREFIX*users` WHERE `uid` = ?" );
	$result = $query->execute( array( $uid ))->fetchRow();
	
	if( !$result ) {
		$ret['error'] = "User not found";
	}
	else{
		$ret['password'] = $result['password'];
	}
	
	OC_Log::write('files_sharding', 'Giving out password hash', OC_Log::WARN);

	
	//OCP\JSON::encodedPrint(Session::unserialize($data));
	OCP\JSON::encodedPrint($ret);
