<?php

try {
	$inc = "../../lib/base.php";
	if(file_exists($inc) && is_readable($inc)){
		require_once '../../lib/base.php';
	}
	else{
		require_once 'base.php';
	}
	require_once 'lib/lib_chooser.php';
	require_once('apps/files_sharding/lib/lib_files_sharding.php');
	
	\OCP\Util::writeLog('status', 'REQUEST: '.$_SERVER['REQUEST_URI'].'-->'.serialize($_REQUEST), \OC_Log::WARN);
	\OCP\Util::writeLog('status', 'HEADERS: '.serialize(getallheaders()), \OC_Log::WARN);
	
	$extraRoot = empty($_REQUEST['extraroot'])?"":$_REQUEST['extraroot'];
	
	$nowDate = new \DateTime();
	$now = $nowDate->getTimestamp();
	
	// Polling
	if(!empty($_POST['token'])){
		$user = \OC_Chooser::validateToken($_POST['token']);
		\OCP\Util::writeLog('status', 'Checking for poll...'.$user, \OC_Log::WARN);
		if(!empty($user) && $user!='guest'){
			\OC_Chooser::deleteToken($_POST['token']);
			/*$server = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http").
				"://".$_SERVER[HTTP_HOST].OC::$WEBROOT.$extraRoot;*/
			$server = \OCA\FilesSharding\Lib::getServerForUser($user);
			$values=array(
				"server"=>rtrim($server, "/")."/".(empty($extraRoot)?"":$extraRoot),
				"loginName"=>$user,
				"appPassword"=>$_POST['token']
			);
			header("Access-Control-Allow-Origin: *");
			header("Content-Type: application/json");
			echo json_encode($values, JSON_UNESCAPED_SLASHES);
			exit();
		}
		else{
			header('HTTP/1.0 401 Unauthorized');
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
			OC_Log::write('chooser', 'Token ok: '.$user.':'.$deviceName.':'.$token, OC_Log::WARN);
			$ret['message'] = "Device added with token ".$token;
			OCP\JSON::encodedPrint($ret, JSON_UNESCAPED_SLASHES);
		}
		else{
			OC_Log::write('chooser', 'Token invalid: '.$token, OC_Log::WARN);
			header('HTTP/1.0 401 Unauthorized');
			$ret['error'] = "Invalid token ".$token;
			OCP\JSON::encodedPrint($ret, JSON_UNESCAPED_SLASHES);
			exit();
		}
	}
	// Landing here after login via mod_rewrite and the redirect below.
	elseif(!empty($_GET['token']) && \OC_User::isLoggedIn()){
		$user = \OCP\User::getUser();
		$token = $_GET['token'];
		$device_tokens = \OC_Chooser::getDeviceTokens($user, true);
		$server = OCA\FilesSharding\Lib::getServerForUser($user);
		$server = rtrim($server, "/")."/".(empty($extraRoot)?"":$extraRoot);
		$tmpl = new OCP\Template('chooser', 'device_token', 'guest');
		$tmpl->assign('user', $user);
		$tmpl->assign('server', $server);
		$tmpl->assign('device_tokens', $device_tokens);
		$tmpl->assign('device_token', $token);
		if(!empty($_GET['flow'])){
			$tmpl->assign('flow', "true");
		}
		\OC_Util::addScript( 'chooser', 'device_token' );
		\OC_Util::addStyle( 'chooser', 'device_token' );
		$tmpl->printPage();
	}
	// Redirected here by mod_rewrite of index.php/login/flow - which is called by the Nextcloud Android client
	elseif(isset($_GET['orig_uri']) && ($_GET['orig_uri']==trim((OC::$WEBROOT.'/index.php/login/flow'), '/') ||
			!empty($extraRoot) && $_GET['orig_uri']==trim(OC::$WEBROOT.$extraRoot.'/index.php/login/flow', '/'))){
		/*if(stripos($_SERVER['HTTP_USER_AGENT'], "iOS")!==false){
			header('HTTP/1.0 404 Not Found');
			exit();
		}*/
		$token = ''.md5(uniqid(mt_rand(), true));
		\OC_Chooser::setToken('guest', 'token_'.$token.'_'.$now, $token);
		\OC_Response::redirect(OC::$WEBROOT."/?redirect_url=".OC::$WEBROOT.
				"/apps/chooser/login.php?token=".$token."&flow=true".(empty($extraRoot)?"":"&extraroot=$extraRoot"));
	}
	elseif(!empty($_GET['server'])&&!empty($_GET['user'])&&!empty($_GET['password'])){
		\OC_Response::redirect(
				"nc://login/server:".$_GET['server']."&user:".$_GET['user']."&password:".$_GET['password']);
		exit();
	}
	// Redirected here by mod_rewrite of index.php/login/v2 - which is called by the Nextcloud iPhone client
	else{
		// Used to prove this is indeed the client that will be authenticated above
		$pollingValue = json_encode(\OC_Chooser::pollingValues($extraRoot), JSON_UNESCAPED_SLASHES);
		\OCP\Util::writeLog('remote', 'Returning json to poller: ' . $pollingValue, \OCP\Util::WARN);
		echo $pollingValue;
		exit;
	}
} catch (Exception $ex) {
	OC_Response::setStatus(OC_Response::STATUS_INTERNAL_SERVER_ERROR);
	\OCP\Util::writeLog('remote', $ex->getMessage(), \OCP\Util::FATAL);
}
