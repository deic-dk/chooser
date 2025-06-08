<?php

/**
* ownCloud
*
* Original:
* @author Frank Karlitschek
* @copyright 2012 Frank Karlitschek frank@owncloud.org
* 
* Adapted:
* @author Michiel de Jong, 2011
*
* Adapted:
* @author Frederik Orellana, 2013
*
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
* License as published by the Free Software Foundation; either
* version 3 of the License, or any later version.
*
* This library is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU AFFERO GENERAL PUBLIC LICENSE for more details.
*
* You should have received a copy of the GNU Affero General Public
* License along with this library.  If not, see <http://www.gnu.org/licenses/>.
*
*/

// curl --insecure --request PROPFIND https://10.2.0.254/remote.php/mydav/test/

OC_Log::write('chooser','HEADERS: '.serialize(getallheaders()), OC_Log::INFO);

require_once 'chooser/lib/ip_auth.php';
require_once 'chooser/lib/x509_auth.php';
require_once 'chooser/lib/device_auth.php';
require_once 'chooser/lib/share_auth.php';
require_once 'chooser/lib/nbf_auth.php';
require_once 'chooser/lib/server.php';
require_once 'chooser/lib/share_objecttree.php';

OC_App::loadApps(array('filesystem','authentication'));

OCP\App::checkAppEnabled('chooser');

session_write_close();

// This may be a browser accessing a webdav URL - and the browser may already be logged in
$loggedInUser = \OCP\USER::getUser();

if(OCP\App::isEnabled('user_group_admin')){
	OC::$CLASSPATH['OC_User_Group_Admin_Backend'] ='apps/user_group_admin/lib/backend.php';
	OC_Group::useBackend( new OC_User_Group_Admin_Backend() );
}

ini_set('default_charset', 'UTF-8');
//ini_set('error_reporting', '');
@ob_clean();

// only need authentication apps
$RUNTIME_APPTYPES=array('authentication');
OC_App::loadApps($RUNTIME_APPTYPES);

OC_Util::obEnd();

//OC_Util::setupFS($ownCloudUser);

// Create ownCloud Dir
//$rootDir = new OC_Connector_Sabre_Directory('');
//$objectTree = new \OC\Connector\Sabre\ObjectTree($rootDir);
if(empty($_SERVER['OBJECT_TREE'])){
	$objectTree = new Share_ObjectTree();
}
else{
	$objectTree = new $_SERVER['OBJECT_TREE']();
}
//$objectTree = new \OC\Connector\Sabre\ObjectTree();

//$server = new Sabre_DAV_Server($rootDir);
if(empty($_SERVER['DAV_SERVER'])){
	$server = new OC_Connector_Sabre_Server_chooser($objectTree);
}
else{
	$server = new $_SERVER['DAV_SERVER']($objectTree);
}

$requestBackend = new OC_Connector_Sabre_Request();
$server->httpRequest = $requestBackend;

$favoriteLink = false;

// Path
//$baseuri = OC_App::getAppWebPath('chooser').'appinfo/remote.php';
$baseuri = OC::$WEBROOT."/remote.php/mydav";
// Known aliases
if(strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/files/")===0 ||
		$_SERVER['REQUEST_URI']==OC::$WEBROOT."/files"){
	$baseuri = OC::$WEBROOT."/files";
}
elseif(strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/public/")===0){
	$baseuri = OC::$WEBROOT."/public";
}

$user = \OC_User::getUser();
if(empty($user)){
	if(empty($_SERVER['PHP_AUTH_USER']) && empty($_SERVER['SSL_CLIENT_I_DN']) &&
			empty($_SERVER['REDIRECT_SSL_CLIENT_S_DN'])){
		$headers = apache_request_headers();
		\OCP\Util::writeLog('chooser','ERROR:  No user for webdav request '.$_SERVER['REQUEST_URI'].'. Headers: '.serialize($headers), \OCP\Util::ERROR);
	}
	if(!empty($_SERVER['PHP_AUTH_USER'])){
		$user = $_SERVER['PHP_AUTH_USER'];
	}
}

// TODO: more thorough check. Currently the favorites call from iOS
// seems to be the only one using REPORT. We can't rely on that in the future.
if((rawurldecode($_SERVER['REQUEST_URI'])==OC::$WEBROOT."/remote.php/dav/files/".
		$user ||
		strpos(rawurldecode($_SERVER['REQUEST_URI']), OC::$WEBROOT."/remote.php/dav/files/".
		$user."/")===0) &&
		strtolower($_SERVER['REQUEST_METHOD'])=='report'){
			$_SERVER['REQUEST_URI'] = rawurldecode($_SERVER['REQUEST_URI']);
	$baseuri = OC::$WEBROOT."/remote.php/dav/files/".$user;
	$objectTree->favorites = true;
}
elseif($_SERVER['REQUEST_URI']==OC::$WEBROOT."/remote.php/dav" /*&&
		strtolower($_SERVER['REQUEST_METHOD'])=='head'*/){
	$baseuri = OC::$WEBROOT."/remote.php/dav";
}
elseif(strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/grid")===0){
	$baseuri = OC::$WEBROOT."/grid";
}
elseif((rawurldecode($_SERVER['REQUEST_URI'])==OC::$WEBROOT."/remote.php/dav/files/".
		$user || strpos(rawurldecode($_SERVER['REQUEST_URI']), OC::$WEBROOT."/remote.php/dav/files/".
		$user."/")===0) /*&&
		strtolower($_SERVER['REQUEST_METHOD'])=='proppatch'*/){
	$_SERVER['REQUEST_URI'] = rawurldecode($_SERVER['REQUEST_URI']);
	$baseuri = OC::$WEBROOT."/remote.php/dav/files/".$user;
}
elseif((rawurldecode($_SERVER['REQUEST_URI'])==OC::$WEBROOT."/remote.php/dav/uploads/".
		$user || strpos(rawurldecode($_SERVER['REQUEST_URI']), OC::$WEBROOT."/remote.php/dav/uploads/".
				$user."/")===0)){
	$_SERVER['REQUEST_URI'] = rawurldecode($_SERVER['REQUEST_URI']);
	if(strlen($_SERVER['REQUEST_URI'])>4 && substr($_SERVER['REQUEST_URI'], -5, 5)=='.file'){
		$_SERVER['REQUEST_URI'] = preg_replace("|^".OC::$WEBROOT."/remote.php/dav/uploads/".$user."/|",
				OC::$WEBROOT."/remote.php/dav/files/".$user."/", $_SERVER['REQUEST_URI']);
		$baseuri = OC::$WEBROOT."/remote.php/dav/files/".$user;
	}
	else{
		$baseuri = OC::$WEBROOT."/remote.php/dav/uploads/".$user;
	}
}
elseif(rawurldecode($_SERVER['REQUEST_URI'])==OC::$WEBROOT."/sharingin/remote.php/dav/files/".
		$user || strpos(rawurldecode($_SERVER['REQUEST_URI']), OC::$WEBROOT."/sharingin/remote.php/dav/files/".
		$user."/")===0){
	$_SERVER['REQUEST_URI'] = rawurldecode($_SERVER['REQUEST_URI']);
	$baseuri = OC::$WEBROOT."/sharingin/remote.php/dav/files/".$user;
	$objectTree->sharingIn = true;
}
elseif(strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/sharingin/remote.php/webdav")===0){
	$baseuri = OC::$WEBROOT."/sharingin/remote.php/webdav";
	$objectTree->sharingIn = true;
	//$objectTree->allowUpload = false;
}
elseif(strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/sharingin/")===0){
	$baseuri = OC::$WEBROOT."/sharingin";
	$objectTree->sharingIn = true;
	//$objectTree->allowUpload = false;
}
elseif(strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/remote.php/dav/files/".
		$user."/@@/sharingin/")===0){
			$baseuri = OC::$WEBROOT."/remote.php/dav/files/".$user."/@@/sharingin";
		$objectTree->sharingIn = true;
		//$objectTree->allowUpload = false;
		$favoriteLink = true;
}
elseif(strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/sharingout/")===0){
	$baseuri = OC::$WEBROOT."/sharingout";
	if(strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/sharingout/@@/")===0){
		$_SERVER['REQUEST_URI'] = preg_replace("|^".OC::$WEBROOT."/sharingout/\@\@/|",
				OC::$WEBROOT."/sharingout/", $_SERVER['REQUEST_URI']);
		$objectTree->sharingInOut = true;
	}
	$objectTree->sharingOut = true;
}
elseif(strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/groupfolders/")===0){
	$group = preg_replace("|^".OC::$WEBROOT."/groupfolders/|", "", $_SERVER['REQUEST_URI']);
	$group = preg_replace("|/.*$|", "", $group);
	// Ignored as empty($_SERVER['BASE_URI'] is set by user_group_admin
	$baseuri = OC::$WEBROOT."/groupfolders/";
	$_SERVER['BASE_DIR'] = '/'.$user.'/user_group_admin/';
}
elseif(strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/remote.php/dav/files/".
		$user."/@@/groupfolders/")===0){
	$group = preg_replace("|^".OC::$WEBROOT."/remote.php/dav/files/".
			$user."/\@\@/groupfolders/|", "", $_SERVER['REQUEST_URI']);
	$group = preg_replace("|/.*$|", "", $group);
	$baseuri = OC::$WEBROOT."/remote.php/dav/files/".$user."/@@/groupfolders/".$group;
	$_SERVER['BASE_DIR'] = '/'.$user.'/user_group_admin/'.$group;
	$favoriteLink = true;
}
$server->setBaseUri($baseuri);

OC_Log::write('chooser','BASE URI: '.$baseuri.':'.
		(empty($_SERVER['BASE_URI'])?'':$_SERVER['BASE_URI']).
		':'.$server->getBaseUri().':'.$_SERVER['REQUEST_URI'].
		'<->'.OC::$WEBROOT."/sharingout/", OC_Log::INFO);

// Auth backends
$defaults = new OC_Defaults();
$name = $defaults->getName();

//$_SERVER['REQUEST_URI'] = preg_replace("/^\/public/", "/remote.php/mydav/", $_SERVER['REQUEST_URI']);

$authBackendIP = new Sabre\DAV\Auth\Backend\IP();
$authPluginIP = new Sabre\DAV\Auth\Plugin($authBackendIP, $name);
$server->addPlugin($authPluginIP);

$authBackendX509 = new Sabre\DAV\Auth\Backend\X509();
$authPluginX509 = new Sabre\DAV\Auth\Plugin($authBackendX509, $name);
$server->addPlugin($authPluginX509);

$authBackendDevice = new Sabre\DAV\Auth\Backend\Device($baseuri);
$authPluginDevice = new Sabre\DAV\Auth\Plugin($authBackendDevice, $name);
$server->addPlugin($authPluginDevice);

//if(strpos($_SERVER['REQUEST_URI'], "/files/")!==0){
if($baseuri == OC::$WEBROOT."/public" || $baseuri == OC::$WEBROOT."/sharingout"){
	OC_Log::write('chooser','REQUEST '.$_SERVER['REQUEST_URI'], OC_Log::WARN);
	$authBackendShare = new Sabre\DAV\Auth\Backend\Share($baseuri);
	$authPluginShare = new Sabre\DAV\Auth\Plugin($authBackendShare, $name);
	$server->addPlugin($authPluginShare);

	if($authBackendShare->path!==null){
		//$_SERVER['REQUEST_URI'] = $baseuri."/".$authBackendShare->path;
		//$server->setBaseUri($baseuri."/".$authBackendShare->token);
		$objectTree->auth_token = $authBackendShare->token;
		$objectTree->auth_path = $authBackendShare->path;
	}
	$objectTree->allowUpload = $authBackendShare->allowUpload;
}
elseif($baseuri != OC::$WEBROOT."/sharingin"){
	$authBackendNBF = new OC_Connector_Sabre_Auth_NBF();
	$authPluginNBF = new Sabre\DAV\Auth\Plugin($authBackendNBF, $name);
	$server->addPlugin($authPluginNBF);
}

// This is to support cookie/web auth by sync clients
if(empty($_SERVER['PHP_AUTH_USER']) && empty($_SERVER['SSL_CLIENT_I_DN']) &&
		empty($_SERVER['REDIRECT_SSL_CLIENT_S_DN'])){
	$authBackend = new OC_Connector_Sabre_Auth();
	$server->addPlugin(new \Sabre\DAV\Auth\Plugin($authBackend, $name));
}

$user = \OC_User::getUser();
if(empty($user) && !empty($_SERVER['PHP_AUTH_USER'])){
	$user = $_SERVER['PHP_AUTH_USER'];
}
if(empty($user) && $baseuri == OC::$WEBROOT."/sharingout"){
	OC_Log::write('chooser','ERROR: no user '.serialize($_SERVER), OC_Log::WARN);
	$server->httpResponse->setHeader('WWW-Authenticate', 'Basic realm="Share"');
	$server->httpResponse->sendStatus(401);
	exit;
}

// Also make sure there is a 'data' directory, writable by the server. This directory is used to store information about locks
$lockBackend = new OC_Connector_Sabre_Locks();
$lockPlugin = new Sabre\DAV\Locks\Plugin($lockBackend);
$server->addPlugin($lockPlugin);

$server->addPlugin(new \Sabre\DAV\Browser\Plugin(false)); // Show something in the Browser, but no upload

$server->xmlNamespaces[\OC_Connector_Sabre_Server_chooser::NS_NEXTCLOUD] = 'nc';
$server->addPlugin(new OC_Connector_Sabre_FilesPlugin());
//$server->addPlugin(new OC_Connector_Sabre_AbortedUploadDetectionPlugin());

$server->addPlugin(new OC_Connector_Sabre_MaintenancePlugin());
$server->addPlugin(new OC_Connector_Sabre_ExceptionLoggerPlugin('davs'));

// Accept mod_rewrite internal redirects.
if(!$favoriteLink && empty($objectTree->favorites)){
	$_SERVER['REQUEST_URI'] = preg_replace("|^".OC::$WEBROOT."/*remote.php/webdav|",
		OC::$WEBROOT."/remote.php/mydav/", $_SERVER['REQUEST_URI']);
	//$_SERVER['REQUEST_URI'] = preg_replace("|^".OC::$WEBROOT."/*remote.php/dav/files/".
	//	/*$authPlugin->getCurrentUser()*/$user."|", OC::$WEBROOT."/remote.php/mydav/",
	//	$_SERVER['REQUEST_URI']);
	$_SERVER['REQUEST_URI'] = preg_replace("|^".OC::$WEBROOT."/*remote.php/davs|",
			OC::$WEBROOT."/remote.php/mydav/", $_SERVER['REQUEST_URI']);
	//$_SERVER['REQUEST_URI'] = preg_replace("|^".OC::$WEBROOT."/*remote.php/dav|",
		//OC::$WEBROOT."/remote.php/mydav/", $_SERVER['REQUEST_URI']);
}

// Accept include by remote.php from files_sharding.
$_SERVER['REQUEST_URI'] = preg_replace("|^".OC::$WEBROOT."/*remote.php/davs|",
		OC::$WEBROOT."/remote.php/mydav/", $_SERVER['REQUEST_URI']);
//$_SERVER['REQUEST_URI'] = preg_replace("/^\/files/", "/remote.php/mydav/", $_SERVER['REQUEST_URI']);
//OC_Log::write('chooser','REQUEST '.serialize($_SERVER), OC_Log::WARN);
//OC_Log::write('chooser','user '.$authPlugin->getCurrentUser(), OC_Log::WARN);

$userServerAccess = \OCA\FilesSharding\Lib::$USER_ACCESS_ALL;
if(!empty($_SERVER['BASE_URI'])){
	// Accept include from remote.php from other apps and set root accordingly
	if($_SERVER['BASE_URI']==OC::$WEBROOT."/remote.php/usage"){
		if(strpos(rtrim($_SERVER['REQUEST_URI'],'/'), OC::$WEBROOT."/remote.php/usage/remote.php/webdav")===0){
			$objectTree->usage = true;
			$_SERVER['REQUEST_URI'] = preg_replace("|^".OC::$WEBROOT."/remote.php/usage/remote.php/webdav|",
					OC::$WEBROOT."/remote.php/usage", $_SERVER['REQUEST_URI']);
		}
		$userServerAccess = \OCA\FilesSharding\Lib::$USER_ACCESS_READ_ONLY;
	}
	if($_SERVER['BASE_URI']==OC::$WEBROOT."/remote.php/groupfolders"){
		if(strpos(rtrim($_SERVER['REQUEST_URI'],'/'), OC::$WEBROOT."/remote.php/groupfolders/remote.php/webdav")===0){
			$objectTree->group = true;
			$_SERVER['REQUEST_URI'] = preg_replace("|^".OC::$WEBROOT."/remote.php/groupfolders/remote.php/webdav|",
					OC::$WEBROOT."/remote.php/groupfolders", $_SERVER['REQUEST_URI']);
		}
		$userServerAccess = \OCA\FilesSharding\Lib::$USER_ACCESS_READ_ONLY;
	}
	$server->setBaseUri($_SERVER['BASE_URI']);
}

// Set by files_sharding/remote.php
if(!empty($_SERVER['READ_ONLY'])){
	$userServerAccess = \OCA\FilesSharding\Lib::$USER_ACCESS_READ_ONLY;
}

// In the case of a move request, a header will contain the destination
// with hard-wired host name.
if(!empty($_SERVER['HTTP_DESTINATION'])){
	$_SERVER['HTTP_DESTINATION'] = rawurldecode($_SERVER['HTTP_DESTINATION']);
	$_SERVER['HTTP_DESTINATION'] = preg_replace("|^(https*://[^/]+)".OC::$WEBROOT."/*remote.php/webdav|",
			"$1".OC::$WEBROOT."/remote.php/mydav/", $_SERVER['HTTP_DESTINATION']);
	// Accept include by remote.php from files_sharding.
	$_SERVER['HTTP_DESTINATION'] = preg_replace("|^(https*://[^/]+)".OC::$WEBROOT."/*remote.php/davs|",
			"$1".OC::$WEBROOT."/remote.php/mydav/", $_SERVER['HTTP_DESTINATION']);
	$_SERVER['HTTP_DESTINATION'] = preg_replace("|^".OC::$WEBROOT."/*remote.php/webdav|",
			OC::$WEBROOT."/remote.php/mydav/", $_SERVER['HTTP_DESTINATION']);
	// Accept include by remote.php from files_sharding.
	$_SERVER['HTTP_DESTINATION'] = preg_replace("|^".OC::$WEBROOT."/*remote.php/davs|",
			OC::$WEBROOT."/remote.php/mydav/", $_SERVER['HTTP_DESTINATION']);
	// Support redirects
	if(strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/sharingout/")===0){
		$_SERVER['HTTP_DESTINATION'] = preg_replace("|^(https*://)([^/]+)".OC::$WEBROOT."/sharingin/|",
				"$1".$_SERVER['HTTP_HOST'].OC::$WEBROOT."/sharingout/", $_SERVER['HTTP_DESTINATION']);
	}
	OC_Log::write('chooser','URI: '.$_SERVER['REQUEST_URI'].'. DESTINATION: '.
			$_SERVER['HTTP_DESTINATION'], OC_Log::WARN);
}

//if(method_exists($objectTree, 'updateMeta')){
//	$server->subscribeEvent('afterWriteContent', array($objectTree, 'updateMeta'));
//}

// wait with registering these until auth is handled and the filesystem is setup
$server->subscribeEvent('beforeMethod', function () use ($server, $objectTree) {
	
	$rootDir = null;
	$view = null;
	$mountManager = null;
	if(!empty($objectTree->sharingIn) && $objectTree->sharingIn){
		$objectTree->sharingInInit();
	}
	elseif(!empty($objectTree->sharingOut) && $objectTree->sharingOut){
		//OC_Hook::clear('OC_Filesystem', 'post_write');
		$objectTree->sharingOutInit();
	}
	elseif(!empty($objectTree->favorites) && $objectTree->favorites){
		//OC_Hook::clear('OC_Filesystem', 'post_write');
		$objectTree->favoritesInit();
	}
	else{
		if(!empty($_SERVER['BASE_DIR'])){
			$user = \OC_User::getUser();
			if(empty($user)){
				$user = $_SERVER['PHP_AUTH_USER'];
			}
			OC_Log::write('chooser','Non-files access: '.$user.': '.$_SERVER['REQUEST_URI'].':'.OC_Request::requestUri().
					'-->'.$_SERVER['BASE_DIR'], OC_Log::WARN);
			\OC\Files\Filesystem::tearDown();
			\OC\Files\Filesystem::init($user, $_SERVER['BASE_DIR']);
			$view = new \OC\Files\View($_SERVER['BASE_DIR']);
		}
		else{
			$view = \OC\Files\Filesystem::getView();
		}
		if(empty($view)){
			$server->httpResponse->sendStatus(403);
			exit;
		}
		$rootInfo = $view->getFileInfo('');
		
		// Create ownCloud Dir
		$mountManager = \OC\Files\Filesystem::getMountManager();
		$rootDir = new OC_Connector_Sabre_Directory($view, $rootInfo);
		$objectTree->init($rootDir, $view, $mountManager);
		
		// This was to bump up quota if smaller than freequota WITHOUT
		// writing the bigger quota to the DB.
		// Unfortunately it only works for the initial size check.
		// When actually writing, fopen is wrapped with \OC\Files\Stream\Quota::wrap,
		// and the DB quota is checked again.
		/*if(\OCP\App::isEnabled('files_accounting')){
			require_once 'files_accounting/lib/quotaplugin.php';
			$server->addPlugin(new OC_Connector_Sabre_QuotaPlugin_files_accounting($view));
		}
		else{*/
			$server->addPlugin(new OC_Connector_Sabre_QuotaPlugin($view));
		//}
	}
}, 30); // priority 30: after auth (10) and acl(20), before lock(50) and handling the request

require_once('apps/chooser/appinfo/apache_note_user.php');

$ok = true;
if(!$objectTree->sharingOut && \OCP\App::isEnabled('files_sharding')){
	$userServerAccess = \OCA\FilesSharding\Lib::getUserServerAccess();
	// Block all access if account is locked on server
	if($userServerAccess==\OCA\FilesSharding\Lib::$USER_ACCESS_NONE){
		$ok = false;
	}
}

// Block write operations on r/o server
if(\OCP\App::isEnabled('files_sharding') &&
		$userServerAccess==\OCA\FilesSharding\Lib::$USER_ACCESS_READ_ONLY &&
		(strtolower($_SERVER['REQUEST_METHOD'])=='mkcol' || strtolower($_SERVER['REQUEST_METHOD'])=='put' ||
				strtolower($_SERVER['REQUEST_METHOD'])=='move' || strtolower($_SERVER['REQUEST_METHOD'])=='copy' ||
				strtolower($_SERVER['REQUEST_METHOD'])=='delete' ||
		strtolower($_SERVER['REQUEST_METHOD'])=='proppatch')){
	$ok = false;
}

session_write_close();

\OCP\Util::writeLog('chooser','Serving '.$user.' : '.$_SERVER['REQUEST_URI'].'. Headers: '.serialize(apache_request_headers()), \OCP\Util::INFO);

// And off we go!
if($ok){
	// Make sure we don't set a session cookie when serving a shared directory/file.
	if(strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/public/")===0 ||
			strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/shared/")===0 ||
			strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/themes/deic_theme_oc7/apps/files_sharing/")===0 ||
			strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/apps/files_sharing/")===0 ||
			strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/sharingin/")===0 ||
			strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/sharingout/")===0){
		if(!headers_sent()){
			OC_Log::write('chooser','Removing cookies', OC_Log::WARN);
			ini_set('session.use_cookies', '0');
			ini_set('output_buffering', 'Off');
			\OC_Util::teardownFS();
			session_destroy();
			$session_id = session_id();
			unset($_COOKIE[$session_id]);
			header_remove('Set-Cookie');
		}
		else{
			OC_Log::write('chooser','Headers already sent', OC_Log::WARN);
		}
	}
	
	$server->exec();
	
}
else{
	//throw new \Sabre\DAV\Exception\Forbidden($_SERVER['REQUEST_METHOD'].' currently not allowed.');
	$server->httpResponse->sendStatus(403);
}

// Deal with browsers
$user_id = \OCP\USER::getUser();
if(!empty($loggedInUser) && $loggedInUser!=$user_id){
	\OC_Util::teardownFS();
	\OC_User::setUserId($loggedInUser);
	\OC_Util::setupFS($loggedInUser);
}
elseif(session_status()===PHP_SESSION_ACTIVE &&
		// This is to avoid that the connectivity checks of the admin page
		// flush the session.
		(empty($_SERVER['HTTP_REFERER']) || substr($_SERVER['HTTP_REFERER'], -25)!="/index.php/settings/admin") &&
		(empty($_SERVER['HTTP_USER_AGENT']) ||
				stripos($_SERVER['HTTP_USER_AGENT'], "ios")===false && stripos($_SERVER['HTTP_USER_AGENT'], "android")===false)){
	//session_destroy();
	//$session_id = session_id();
	//unset($_COOKIE[$session_id]);
}

