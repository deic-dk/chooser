<?php

class OC_Chooser {

	private static $uservlannets = null;
	private static $trustednets = null;
	private static $vlanlisturl = null;
	private static $vlanlistpassword = null;
	private static $vlantrusteduser = null;
	private static $IPS_TTL_SECONDS = 30;
	private static $IPS_CACHE_KEY = 'compute_ips';
	private static $STORAGE_TOKEN_DEVICE_NAME = 'storage';
	private static $trusted_dn_header_host_dns = null;
	public static $dn_header = null;
	private static $dnHeaderHostDNs = [];
	
	public static $MAX_CERTS = 10;
	public static $MOVING_CACHE_PREFIX = 'moving_';
	
	private static function loadNetValues(){
		if(self::$trustednets===null){
			$tnet = \OCP\Config::getSystemValue('trustednet', '');
			$tnet = trim($tnet);
			$tnets = explode(' ', $tnet);
			self::$trustednets = array_map('trim', $tnets);
			if(count(self::$trustednets)==1 && substr(self::$trustednets[0], 0, 8)==='TRUSTED_'){
				self::$trustednets = [];
			}
		}
		if(self::$uservlannets===null){
			$tnet = \OCP\Config::getSystemValue('uservlannet', '');
			$tnet = trim($tnet);
			$tnets = explode(' ', $tnet);
			self::$uservlannets = array_map('trim', $tnets);
			if(count(self::$uservlannets)==1 && substr(self::$uservlannets[0], 0, 10)==='USER_VLAN_'){
				self::$uservlannets = [];
			}
		}
		if(self::$vlanlisturl===null){
			self::$vlanlisturl = OC_Appconfig::getValue('user_pods', 'getContainersURL');
		}
		if(self::$vlantrusteduser===null){
			self::$vlantrusteduser = OC_Appconfig::getValue('user_pods', 'trustedUser');
		}
		// We now use Kubernetes to fire up user containers. And they no longer each have a vlan, but
		// just a 10.2 ip address. So the word vlan is misleading, but we keep it for now...
		if(self::$vlanlistpassword===null){
			self::$vlanlistpassword = OC_Appconfig::getValue('user_pods', 'getContainersPassword');
			
		}
	}
	
	public static function checkTrusted($remoteIP){
		self::loadNetValues();
		foreach(self::$trustednets as $trustednet){
			if(!empty($remoteIP) && strpos($remoteIP, $trustednet)===0){
				return true;
			}
		}
		return false;
	}
	
	public static function privateToUserVlan($url){
		$parsedUrl = parse_url($url);
		$ip = $parsedUrl['host'];
		self::loadNetValues();
		// We're assuming one uservlannet and one trustednet
		if(empty(self::$uservlannets) || empty(self::$trustednets) ||
				count(self::$uservlannets)>1 || count(self::$trustednets)>1){
					OC_Log::write('chooser', 'Cannot resolve '.$ip.' to '.
							serialize(self::$uservlannets).count(self::$uservlannets).count(self::$trustednets), OC_Log::ERROR);
			return null;
		}
		if(!self::checkTrusted($ip)){
			OC_Log::write('chooser', 'Not trusted: '.$url, OC_Log::ERROR);
			return null;
		}
		$userDigits = explode('.', self::$uservlannets[0]);
		$trustedDigits = explode('.', self::$trustednets[0]);
		$maxDigits = count($userDigits)<count($trustedDigits)?count($userDigits):count($trustedDigits);
		$userBase = implode('.', array_slice($userDigits, 0, $maxDigits));
		$trustedBase = implode('.', array_slice($trustedDigits, 0, $maxDigits));
		$userVlanIP = preg_replace('|^'.$trustedBase.'|', $userBase, $ip);
		$userVlanURL = $parsedUrl['scheme'].'://'.$userVlanIP.$parsedUrl['path'].
			(empty($parsedUrl['query'])?'':'?'.$parsedUrl['query']).
			(empty($parsedUrl['anchor'])?'':'#'.$parsedUrl['anchor']);
		return $userVlanURL;
	}
	
	private static function checkUserVlan($remoteIP){
		self::loadNetValues();
		foreach(self::$uservlannets as $vlannet){
			if(!empty($remoteIP) && !empty($vlannet) && strpos($remoteIP, $vlannet)===0){
				return true;
			}
		}
		return false;
	}

	public static function checkIP(){
		if(isset($_SERVER['REMOTE_ADDR']) && self::checkUserVlan($_SERVER['REMOTE_ADDR']) ||
				($_SERVER['REMOTE_ADDR']=="localhost" || $_SERVER['REMOTE_ADDR']=="127.0.0.1")){
			$user_id = '';
			$list_array = [];
			if(!empty(self::$vlanlisturl) && ($list_array = apc_fetch(self::$IPS_CACHE_KEY)) === false){
				$list_line = file_get_contents(self::$vlanlisturl.'?password='.self::$vlanlistpassword);
				$list_array = explode("\n", $list_line);
				apc_add(self::$IPS_CACHE_KEY, $list_array, self::$IPS_TTL_SECONDS);
				OC_Log::write('chooser', 'Refreshed IP cache: '.$list_array[0], OC_Log::WARN);
			}
			foreach($list_array as $line){
				$entries = explode("|", $line);
				if(count($entries)<2){
					continue;
				}
				// pod_name|container_name|image_name|pod_ip|node_ip|owner|age(s)|status|ssh_port|ssh_username|https_port|uri
				$ip = trim($entries[3]);
				$owner = trim($entries[5]);
				OC_Log::write('chooser', 'IP '.$_SERVER['REMOTE_ADDR'].' : '.$ip.' : '.$owner.' : '.$my_private_ip, OC_Log::INFO);
				// Request from user container or vm for /files/ or other php-served URL
				if(!empty($ip) && !empty($owner) && !empty($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']==$ip){
					OC_Log::write('chooser', 'CHECK IP: '.$ip.":".$owner, OC_Log::INFO);
					// We allow requests from pods owned by 'cloud' to set any user in Apache auth
					if(!empty($_SERVER['PHP_AUTH_USER']) && $owner == self::$vlantrusteduser){
						$user_id = $_SERVER['PHP_AUTH_USER'];
					}
					else{
						$user_id = $owner;
					}
					\OC::$session->set('user_id', $user_id);
					break;
				}
				// Request from localhost to verify request from user container for
				// /storage/ - served by Apache
				$my_internal_url = \OCA\FilesSharding\Lib::getServerForUser($owner, true);
				$my_private_url = \OCA\FilesSharding\Lib::internalToPrivate($my_internal_url);
				$parse = parse_url($my_private_url);
				$my_private_ip = $parse['host'];
				if(!empty($_SERVER['REMOTE_ADDR']) && ($_SERVER['REMOTE_ADDR']=="localhost" ||
						$_SERVER['REMOTE_ADDR']=="127.0.0.1" || $_SERVER['REMOTE_ADDR']==$my_private_ip) &&
						!empty($ip) && !empty($owner) && !empty($_SERVER['HTTP_IP']) && $_SERVER['HTTP_IP']==$ip){
							OC_Log::write('chooser', 'CHECK IP: '.$ip.":".$owner, OC_Log::INFO);
					if(!empty($_SERVER['PHP_AUTH_USER']) && $owner == self::$vlantrusteduser){
						$user_id = $_SERVER['PHP_AUTH_USER'];
					}
					else{
						$user_id = $owner;
					}
					\OC::$session->set('user_id', $user_id);
					break;
				}
			}
			if(!empty($user_id) && /*this is the owner of the pod*/ $owner!=self::$vlantrusteduser){
				$internalDavDir = trim(trim(self::getInternalDavDir($user_id), '/')).'/';
				$internalDavDir = preg_replace('|//+|', '/', $internalDavDir);
				$requestPath = trim($_SERVER['REQUEST_URI'], '/');
				$requestPath = preg_replace('|^files/+|', '', $requestPath);
				$requestPath = preg_replace('|//+|', '/', $requestPath);
				if(self::getInternalDavEnabled($user_id)!='yes' ||
						!empty($internalDavDir) && $internalDavDir!='/' &&
						strpos($requestPath, $internalDavDir)!==0 &&
						$internalDavDir!=$requestPath && $internalDavDir!=$requestPath.'/'){
							OC_Log::write('chooser', 'Declining request for '.$requestPath.
									' outside of allowed path '.$internalDavDir.' for '.$user_id.':'.
									self::getInternalDavEnabled($user_id), OC_Log::WARN);
							return '';
				}
			}
			OC_Log::write('chooser', 'user_id: '.$user_id, OC_Log::DEBUG);
			return $user_id;
		}
		elseif(isset($_SERVER['REMOTE_ADDR']) && self::checkTrusted($_SERVER['REMOTE_ADDR'])){
			if(isset($_SERVER['PHP_AUTH_USER']) && \OC_User::userExists($_SERVER['PHP_AUTH_USER'])){
				OC_Log::write('chooser', 'user_id: '.$_SERVER['PHP_AUTH_USER'], OC_Log::DEBUG);
				
				// Block write operations from backup servers (cmd-line sync client mess-up)
				if((strtolower($_SERVER['REQUEST_METHOD'])=='mkcol' || strtolower($_SERVER['REQUEST_METHOD'])=='put' ||
						strtolower($_SERVER['REQUEST_METHOD'])=='move' || strtolower($_SERVER['REQUEST_METHOD'])=='copy' ||
						strtolower($_SERVER['REQUEST_METHOD'])=='delete' ||
						strtolower($_SERVER['REQUEST_METHOD'])=='proppatch') &&
						!empty($_SERVER['HTTP_USER_AGENT']) &&
						stripos($_SERVER['HTTP_USER_AGENT'], "mirall")!==false &&
						stripos($_SERVER['HTTP_USER_AGENT'], "freebsd")!==false){
					OC_Log::write('chooser', 'Blocking write request from backup server for '.$_SERVER['PHP_AUTH_USER'],
						OC_Log::ERROR);
							header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
							exit();
				}
				
				\OC::$session->set('user_id', $_SERVER['PHP_AUTH_USER']);
				return $_SERVER['PHP_AUTH_USER'];
			}
		}
		return "";
	}

	public static function getInternalDavEnabled($user='') {
		if(empty($user)){
			$user = \OCP\USER::getUser();
		}
		return \OCP\Config::getUserValue($user, 'chooser', 'allow_internal_dav', 'no');
	}
	
	public static function getInternalDavDir($user='') {
		if(empty($user)){
			$user = \OCP\USER::getUser();
		}
		return \OCP\Config::getUserValue($user, 'chooser', 'internal_dav_dir', '');
	}

	public static function getStorageEnabled() {
		return \OCP\Config::getUserValue(\OCP\USER::getUser(), 'chooser', 'show_storage_nfs', 'no');
	}
	
	/* $value: 'yes' and 'no'*/
	public static function setInternalDavEnabled($value) {
		if($value != 'yes' && $value != 'no'){
			throw new \Exception("Must be yes or no: $value");
		}
		return \OCP\Config::setUserValue(\OCP\USER::getUser(), 'chooser', 'allow_internal_dav', $value);
	}
	
	public static function setInternalDavDir($value) {
		return \OCP\Config::setUserValue(\OCP\USER::getUser(), 'chooser', 'internal_dav_dir', $value);
	}

	// Copied from files_external
	private static function encryptPassword($password) {
		$cipher = self::getCipher();
		$iv = \OCP\Util::generateRandomBytes(16);
		$cipher->setIV($iv);
		return base64_encode($iv . $cipher->encrypt($password));
	}

	private static function getCipher() {
		// New, php-7, version of phpseclib
		if(is_dir(OC::$SERVERROOT."/3rdparty/phpseclib/phpseclib/phpseclib/Common")){
			include_once('Crypt/AES.php');
			$cipher = new phpseclib3\Crypt\AES('cbc');
			// Our salt is 30 chars - not supported by newer library versions, pad to 32 chars
			$cipher->setKey(\OCP\Config::getSystemValue('passwordsalt')."\0\0");
			return $cipher;
		}
		else{
			include_once('Crypt/AES.php');
			$cipher = new Crypt_AES(CRYPT_AES_MODE_CBC);
			$cipher->setKey(\OCP\Config::getSystemValue('passwordsalt'));
			return $cipher;
		}
	}

	/* $value: 'yes' and 'no'*/
	public static function setStorageEnabled($value) {
		if($value!='yes' && $value!='no'){
			throw new \Exception("Must be yes or no: $value");
		}
		$user = \OCP\USER::getUser();
		\OCP\Config::setUserValue($user, 'chooser', 'show_storage_nfs', $value);
		if($value=='no'){
			// Just leave it
			//self::deleteDeviceToken($user,  self::$STORAGE_TOKEN_DEVICE_NAME);
			return true;
		}
		// else
		$res = true;
		if(!empty(self::getDeviceToken($user, self::$STORAGE_TOKEN_DEVICE_NAME))){
			self::deleteDeviceToken($user, self::$STORAGE_TOKEN_DEVICE_NAME);
		}
		$dataDir = \OC_Config::getValue("datadirectory", \OC::$SERVERROOT . "/data");
		$userDataDir = rtrim($dataDir, '/').'/'.$user;
		$userStorageMountFile = rtrim($userDataDir, '/').'/mount.json';
		$userServer = \OCA\FilesSharding\Lib::getServerForUser($user);
		if(empty($userServer)){
			$userServer = OCA\FilesSharding\Lib::getMasterURL();
		}
		$storageUrl = $userServer."/storage/";
		OC_Log::write('chooser', 'Setting storage URL: . '.$storageUrl, OC_Log::WARN);
		$storageUrlEscaped = str_replace("/", "\/", $storageUrl);
		$storageToken = ''.md5(uniqid(mt_rand(), true));
		$storageTokenEncrypted = self::encryptPassword($storageToken);
		OC_Log::write('chooser', 'Setting device token for '.self::$STORAGE_TOKEN_DEVICE_NAME.':'.$storageToken, OC_Log::WARN);
		self::setDeviceToken($user, self::$STORAGE_TOKEN_DEVICE_NAME, $storageToken);
$storageMountJson = <<<END
{
    "user": {
        "$user": {
            "\/$user\/files_external\/storage": {
                "class": "\\\\OC\\\\Files\\\\Storage\\\\DAV",
                "options": {
                    "host": "$storageUrlEscaped",
                    "user": "$user",
                    "password": "",
                    "root": "\/",
                    "secure": "true",
                    "password_encrypted": "$storageTokenEncrypted"
                },
                "priority": 100
            }
        }
    }
}
END;
		
		OC_Log::write('chooser', 'Writing JSON to: '.$userStorageMountFile, OC_Log::WARN);
		$res = $res && file_put_contents($userStorageMountFile, $storageMountJson);
		return $res;
	}

	private static function getCertIndex($user) {
		$index = 0;
		while($index<self::$MAX_CERTS){
			$subject = \OCP\Config::getUserValue($user, 'chooser', 'ssl_certificate_subject_'.$index);
			if(empty($subject)){
				return $index;
			}
			++$index;
		}
		return -1;
	}

	public static function getCertSubject($user, $index=0){
		return \OCP\Config::getUserValue($user, 'chooser', 'ssl_certificate_subject_'.$index);
	}

	public static function getActiveDNs($user){
		$index = self::getCertIndex($user);
		$dns = [];
		for($i=0; $i<$index; ++$i){
			$dns[] = self::getCertSubject($user, $i);
		}
		return $dns;
	}

	public static function addCert($user, $subject) {
		$index = self::getCertIndex($user);
		if($index<0){
			return false;
		}
		return \OCP\Config::setUserValue($user, 'chooser', 'ssl_certificate_subject_'.$index, $subject);
	}

	public static function removeCert($user, $subject="") {
		if(!empty($subject)){
			$sql = "delete FROM *PREFIX*preferences WHERE userid = ? AND appid = ? AND configkey LIKE ? AND configvalue = ?";
			$args = array($user, 'chooser', 'ssl_certificate_subject_%', $subject);
		}
		else{
			$sql = "delete FROM *PREFIX*preferences WHERE userid = ? AND appid = ? AND configkey LIKE ?";
			$args = array($user, 'chooser', 'ssl_certificate_subject_%');
		}
		$query = \OCP\DB::prepare($sql);
		return $query->execute($args);
	}
	
	private static function getAppDir($user){
		\OC_User::setUserId($user);
		\OC_Util::setupFS($user);
		$fs = \OCP\Files::getStorage('chooser');
		if(!$fs){
			\OC_Log::write('chooser', "ERROR, could not access files of user " . $user, \OC_Log::ERROR);
			return null;
		}
		return $fs->getLocalFile('/');
	}
	
	public static function generateUserCert($days=30, $user="") {
		if(empty($user)){
			$user = \OCP\USER::getUser();
		}
		$output = [];
		$ret = "";
		$certDir = self::getAppDir($user)."ssl";
		if(!file_exists($certDir)){
			mkdir($certDir);
		}
		$secret  = \OC_Config::getValue('secret', 'secret');
		$ca_cert  = \OC_Config::getValue('my_ca_certificate', '');
		$ca_key  = \OC_Config::getValue('my_ca_privatekey', '');
		// Generate encrypted key and cert request in user_id/chooser/ssl/
		$reqStr = "openssl req -new -out \"$certDir/userreq.pem\" -newkey rsa:4096 -keyout \"$certDir/userkey.pem\" -subj \"/CN=$user/O=sciencedata.dk\" -passout pass:$secret";
		OC_Log::write('chooser',"Generating certificate request with: ".$reqStr, OC_Log::WARN);
		exec($reqStr, $output, $ret);
		if($ret==0){
			$signStr = "RANDFILE=/tmp/.random openssl x509 -req -in \"$certDir/userreq.pem\" -CA \"$ca_cert\" -CAkey \"$ca_key\" -CAcreateserial -out \"$certDir/usercert.pem\" -days $days -sha256";
			OC_Log::write('chooser',"Signing certificate request with: ".$signStr, OC_Log::WARN);
			exec($signStr, $output, $ret);
		}
		if($ret==0){
			$expires = exec("openssl x509 -enddate -noout -in \"$certDir/usercert.pem\" | awk -F= '{print $2}'", $output, $ret);
		}
		if($ret==0){
			$ret = [];
			$ret["dn"] = "/CN=$user/O=sciencedata.dk";
			$ret['expires'] = $expires;
			return $ret;
		}
		else{
			\OC_Log::write('chooser', "ERROR, could not generate key/certificate for " . $user.". ".serialize($output), \OC_Log::ERROR);
			return false;
		}
	}
	
	public static function getSDCert($user) {
		$cert = "";
		$certDir = self::getAppDir($user)."ssl";
		if(file_exists("$certDir/usercert.pem")){
			$cert = file_get_contents("$certDir/usercert.pem");
		}
		return $cert;
	}
	
	public static function getSDKey($user) {
		$output = [];
		$ret = "";
		$certDir = self::getAppDir($user)."ssl";
		$secret  = \OC_Config::getValue('secret', 'secret');
		$keyStr = "openssl rsa -in $certDir/userkey.pem -passin pass:".$secret;
		OC_Log::write('chooser',"Executing ".$keyStr, OC_Log::WARN);
		if(file_exists("$certDir/userkey.pem")){
			exec($keyStr, $output, $ret);
		}
		return implode("\n", $output);
	}
	
	public static function getSDPKCS12($user) {
		$output = [];
		$certDir = self::getAppDir($user)."ssl";
		$secret  = \OC_Config::getValue('secret', 'secret');
		if(file_exists("$certDir/usercert.pem") && file_exists("$certDir/userkey.pem")){
			$pkgs12Str = "openssl pkcs12 -export -inkey \"$certDir/userkey.pem\" -in \"$certDir/usercert.pem\" -passout pass: -passin pass:".$secret;
			OC_Log::write('chooser',"Generating PKCS12 output with ".$pkgs12Str, OC_Log::WARN);
			$output = shell_exec($pkgs12Str);
		}
		return $output;
	}
	
	public static function getSDCertSubject($user) {
		$output = "";
		$ret = "";
		$subject = "";
		$certDir = self::getAppDir($user)."ssl";
		if(file_exists("$certDir/usercert.pem")){
			$subject = exec("openssl x509 -subject -noout -in \"$certDir/usercert.pem\" | awk '{print $2}'", $output, $ret);
		}
		return $subject;
	}
	
	public static function getSDCertExpires($user) {
		$output = "";
		$ret = "";
		$expires = "";
		$certDir = self::getAppDir($user)."ssl";
		if(file_exists("$certDir/usercert.pem")){
			$expires = exec("openssl x509 -enddate -noout -in \"$certDir/usercert.pem\" | awk -F= '{print $2}'", $output, $ret);
		}
		return $expires;
	}
	
	public static function getUserFromSubject($subject, $user_=""){
		OC_Log::write('chooser',"Looking for certificate with subject ".$subject." for user ".
				$user_, OC_Log::WARN);
		$i = 0;
		$user = $user_;
		while($i<self::$MAX_CERTS){
			if(empty($user)){
				$sql = "SELECT userid, configvalue FROM *PREFIX*preferences WHERE appid = ? AND configkey = ?";
				$args = array('chooser', 'ssl_certificate_subject_'.$i);
				
			}
			else{
				$sql = "SELECT userid, configvalue FROM *PREFIX*preferences WHERE userid=? AND appid = ? AND configkey = ?";
				$args = array($user, 'chooser', 'ssl_certificate_subject_'.$i);
			}
			$query = \OCP\DB::prepare($sql);
			$output = $query->execute($args);
			$j = 0;
			$checkDNArr = \OCA\FilesSharding\Lib::tokenizeDN($subject);
			while($row=$output->fetchRow()){
				if(!empty($row['configvalue'])){
					$configDNArr = \OCA\FilesSharding\Lib::tokenizeDN($row['configvalue']);
					if($configDNArr==$checkDNArr){
						if($j>0 && $user != $row['userid']){
							OC_Log::write('chooser',"ERROR: subject ".$subject." registered by more than one user:".
									$user. " <-> ".$row['userid'], OC_Log::ERROR);
							return "";
						}
						$user = $row['userid'];
						OC_Log::write('chooser',"Cert: ".$row['configvalue'].":".$row['userid']." <-> ".$subject, OC_Log::WARN);
						++$j;
					}
				}
			}
			++$i;
		}
		return $user;
	}
	
	public static function getDeviceToken($user, $deviceName){
		$sql = "SELECT configkey, configvalue FROM *PREFIX*preferences WHERE userid = ? AND appid = ? AND configkey = ?";
		$args = array($user, 'chooser', 'device_token_'.$deviceName);
		$query = \OCP\DB::prepare($sql);
		$output = $query->execute($args);
		while($row=$output->fetchRow()){
			if(!empty($row['configvalue'])){
				return $row['configvalue'];
			}
		}
		return null;
	}
	
	public static function validateToken($token){
		$sql = "SELECT userid, configkey, configvalue FROM *PREFIX*preferences WHERE appid = ? AND configkey like ?";
		$args = array('chooser', 'token_%');
		$query = \OCP\DB::prepare($sql);
		$output = $query->execute($args);
		$ret = false;
		while($row=$output->fetchRow()){
			if(!empty($row['configvalue']) && $token==$row['configvalue']){
				if($row['userid']!='guest'){
					return $row['userid'];
				}
				else{
					$ret = $row['userid'];
				}
			}
		}
		return $ret;
	}
	
	public static function getDeviceTokens($user, $hideStorageToken=false){
		$result = array();
		if($hideStorageToken){
			$sql = "SELECT configkey, configvalue FROM *PREFIX*preferences WHERE userid = ? AND appid = ? AND configkey LIKE ? AND configkey != ?";
			$args = array($user, 'chooser', 'device_token_%', 'device_token_'.self::$STORAGE_TOKEN_DEVICE_NAME);
		}
		else{
			$sql = "SELECT configkey, configvalue FROM *PREFIX*preferences WHERE userid = ? AND appid = ? AND configkey LIKE ?";
			$args = array($user, 'chooser', 'device_token_%');
		}
		$query = \OCP\DB::prepare($sql);
		$output = $query->execute($args);
		while($row=$output->fetchRow()){
			if(!empty($row['configkey'])){
				$result[$row['configkey']] = $row['configvalue'];
			}
		}
		return $result;
	}
	
	public static function setDeviceToken($user, $deviceName, $token){
		if($deviceName==self::$STORAGE_TOKEN_DEVICE_NAME){
			$deviceName = $deviceName.'_device';
		}
		$forcePortable = (CRYPT_BLOWFISH != 1);
		$hasher = new \PasswordHash(8, $forcePortable);
		$hash = $hasher->HashPassword($token . \OC_Config::getValue('passwordsalt', ''));
		\OCP\Config::setUserValue($user, 'chooser', 'device_token_'.$deviceName, $hash);
		return $token;
	}
	
	public static function deleteDeviceToken($user, $deviceName){
		\OC_Preferences::deleteKey($user, 'chooser', 'device_token_'.$deviceName);
	}
	
	public static function setToken($user, $id, $token){
		\OCP\Config::setUserValue($user, 'chooser', $id, $token);
		return $token;
	}
	
	public static function deleteToken($token){
		$sql = "DELETE FROM *PREFIX*preferences WHERE appid = ? AND configkey LIKE ? AND configvalue = ?";
		$args = array('chooser', 'token_%', $token);
		$query = \OCP\DB::prepare($sql);
		$query->execute($args);
	}
	
	public static function cleanupTokens(){
		$nowDate = new \DateTime();
		$now = $nowDate->getTimestamp();
		$sql = "SELECT configkey, configvalue FROM *PREFIX*preferences WHERE appid = ? AND configkey LIKE ?";
		$args = array('chooser', 'token_%');
		$query = \OCP\DB::prepare($sql);
		$output = $query->execute($args);
		$toDeleteTokenIds = array();
		while($row=$output->fetchRow()){
			if(!empty($row['configkey']) &&
				substr($row['configkey'], 0, strlen('token_'.$row['configvalue']))==
				'token_'.$row['configvalue']){
				$tokenTimeStamp = substr($row['configkey'], strlen('token_')+32/*token*/+1);
				// Discard 'guest' tokens older than one week
				if($now-$tokenTimeStamp>7*24*60*60){
					$toDeleteTokenIds[] = $row['configkey'];
				}
			}
		}
		foreach($toDeleteTokenIds as $id){
			$sql = "DELETE FROM *PREFIX*preferences WHERE userid = ? AND appid = ? AND configkey = ?";
			$args = array('guest', 'chooser', $id);
			$query = \OCP\DB::prepare($sql);
			$query->execute($args);
		}
	}
	
	public static function pollingValues($extraRoot=""){
		//require_once('apps/files_sharding/lib/lib_files_sharding.php');
		$nowDate = new \DateTime();
		$now = $nowDate->getTimestamp();
		$token = ''.md5(uniqid(mt_rand(), true));
		// The token set for device name starting with token is temporary and only used for validating the device above
		\OC_Chooser::setToken('guest', 'token_'.$token.'_'.$now, $token);
		$actual_host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http").
		"://".$_SERVER['HTTP_HOST'];
		$actual_link = $actual_host.preg_replace('|\?.*$|', '', $_SERVER['REQUEST_URI']);
		$values = array(
				//"login"=>\OCA\FilesSharding\Lib::getMasterURL().
				"login"=>$actual_host.
				"?redirect_url=".urlencode(OC::$WEBROOT."/apps/chooser/login.php?".
				(empty($extraRoot)?"":"extraroot=".$extraRoot."&")."token=".$token),
				"poll"=>array("token"=>$token, "endpoint"=>$actual_link));
		return $values;
	}
	
	public static function checkAllowHttp(){
		// Allow non-https requests from user pods.
		// This is a hack: In base.php, checkSSL() is called right after session initialization,
		// i.e. right after this hook is called. checkSSL() ignores forcessl in the config file
		// if it thinks it's running in cli mode.
		if(!empty($_SERVER['SERVER_PROTOCOL']) && stripos($_SERVER['SERVER_PROTOCOL'], 'https')===false &&
				!empty($_SERVER['REQUEST_URI']) && (strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT.'/files/')===0 ||
						$_SERVER['REQUEST_URI']==OC::$WEBROOT.'/files') &&
				!empty($_SERVER['REMOTE_ADDR']) && (self::checkTrusted($_SERVER['REMOTE_ADDR']) ||
				self::checkUserVlan($_SERVER['REMOTE_ADDR']))){
			\OC::$CLI = true;
		}
	}
	
	public static function checkCertRelay($hostDn){
		if(empty($hostDn)){
			return "";
		}
		if(self::$dn_header==null){
			self::$dn_header = \OCP\Config::getSystemValue('dn_header', '');
		}
		if(self::$trusted_dn_header_host_dns==null){
			self::$trusted_dn_header_host_dns = \OCP\Config::getSystemValue('trusted_dn_header_host_dns', '');
		}
		if(empty(self::$dn_header) || empty(self::$trusted_dn_header_host_dns)){
			return "";
		}
		$headers = apache_request_headers();
		$headerDN = trim($headers[self::$dn_header]);
		if(empty($headerDN)){
			return "";
		}
		self::$dnHeaderHostDNs = array_map('trim', explode(",", self::$trusted_dn_header_host_dns));
		$hostDNTokens = \OCA\FilesSharding\Lib::tokenizeDN($hostDn);
		foreach(self::$dnHeaderHostDNs as $dn){
			if(\OCA\FilesSharding\Lib::tokenizeDN($dn) == $hostDNTokens){
				return $headerDN;
			}
		}
		return "";
	}

}
