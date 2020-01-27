<?php


try {

	require_once 'base.php';
	require_once 'lib/lib_chooser.php';
	
	\OCP\Util::writeLog('status', 'REQUEST: '.serialize($_REQUEST), \OC_Log::WARN);
	\OCP\Util::writeLog('status', 'HEADERS: '.serialize(getallheaders()), \OC_Log::WARN);
	$nowDate = new \DateTime();
	$now = $nowDate->getTimestamp();
	
	// Polling
	if(!empty($_POST['token'])){
		$user = \OC_Chooser::validateToken($_POST['token']);
		if(!empty($user) && $user!='guest'){
			\OC_Chooser::deleteToken($_POST['token']);
			$server = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http").
				"://".$_SERVER[HTTP_HOST].OC::$WEBROOT;
			$values=array(
				"server"=>$server,
				"loginName"=>$user,
				"appPassword"=>$_POST['token']
			);
			echo json_encode($values);
		}
		else{
			header('HTTP/1.0 404 Not Allowed');
			exit();
		}
	}
	// Authorizing ajax endpoint called by template below
	elseif(!empty($_GET['token']) && !empty($_GET['set_device_token']) &&
			!empty($_GET['device_name']) && \OC_User::isLoggedIn()){
		$token = $_GET['token'];
		OC_Log::write('chooser', 'Validating token '.$token, OC_Log::WARN);
		if(\OC_Chooser::validateToken($token)){
			// Set token for user instead of 'guest' - will trigger the polling above to succeed
			$user = \OCP\User::getUser();
			$deviceName = $_GET['device_name'];
			\OC_Chooser::deleteToken($token);
			\OC_Chooser::cleanupTokens();
			\OC_Chooser::setToken($user, 'token_'.$token.'_'.$now, $token);
			\OC_Chooser::setDeviceToken($user, $deviceName, $token);
			$ret['message'] = "Device added with token ".$token;
			OCP\JSON::encodedPrint($ret);
		}
		else{
			header('HTTP/1.0 404 Not Allowed');
			$ret['error'] = "Invalid token ".$token;
			OCP\JSON::encodedPrint($ret);
			exit();
		}
	}
	// Landing here after login via mod_rewrite and the redirect below.
	elseif(!empty($_GET['token']) && \OC_User::isLoggedIn()){
		$user = \OCP\User::getUser();
		$token = $_GET['token'];
		$device_tokens = \OC_Chooser::getDeviceTokens($user);
		$server = OCA\FilesSharding\Lib::getServerForUser($user);
		$tmpl = new OCP\Template('chooser', 'device_token', 'guest');
		$tmpl->assign('user', $user);
		$tmpl->assign('server', $server);
		$tmpl->assign('device_tokens', $device_tokens);
		$tmpl->assign('device_token', $token);
		\OC_Util::addScript( 'chooser', 'device_token' );
		\OC_Util::addStyle( 'chooser', 'device_token' );
		$tmpl->printPage();
	}
	// Redirected here by mod_rewrite of index.php/login/v2 - which is called by the Nextcloud client
	else{
		// Used to prove this is indeed the client that will be authenticated above
		$token = ''.md5(uniqid(mt_rand(), true));
		// The token set for device name starting with token is temporary and only used for validating the device above
		\OC_Chooser::setToken('guest', 'token_'.$token.'_'.$now, $token);
		$actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http").
			"://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		$values=array(
			"login"=>\OCA\FilesSharding\Lib::getMasterURL().
				"?redirect_url=".OC::$WEBROOT."/apps/chooser/login.php?token=".$token,
				"poll"=>array("token"=>$token, "endpoint"=>$actual_link));
		echo json_encode($values);
	}

} catch (Exception $ex) {
	OC_Response::setStatus(OC_Response::STATUS_INTERNAL_SERVER_ERROR);
	\OCP\Util::writeLog('remote', $ex->getMessage(), \OCP\Util::FATAL);
}
