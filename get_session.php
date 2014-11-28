<?php

	OCP\App::checkAppEnabled('user_saml');

	include("lib/session.php");

	$id = $_POST['id'];

	$session_save_path = trim(session_save_path());
	if(empty($session_save_path)){
		$session_save_path = "/tmp";
	}
	
	$ret = array();

	$data = file_get_contents($session_save_path."/sess_".$id);
	
	if(!$data){
		$ret['error'] = "File not found";
	}
	else{
		$ret['session'] = $data;
	}
	
	OC_Log::write('files_sharding', 'Passing on session: '.$session_save_path."/sess_".$id." --> ".$data, OC_Log::WARN);

	
	//OCP\JSON::encodedPrint(Session::unserialize($data));
	OCP\JSON::encodedPrint($ret);
