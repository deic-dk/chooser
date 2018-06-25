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

OC_Log::write('chooser','Remote access',OC_Log::DEBUG);

require_once 'chooser/lib/ip_auth.php';
require_once 'chooser/lib/x509_auth.php';
require_once 'chooser/lib/share_auth.php';
require_once 'chooser/lib/nbf_auth.php';
require_once 'chooser/lib/server.php';
require_once 'chooser/lib/share_objecttree.php';

OC_App::loadApps(array('filesystem','authentication'));

OCP\App::checkAppEnabled('chooser');

// This may be a browser accessing a webdav URL - and the browser may already be logged in
if(OC_User::isLoggedIn()){
	$loggedInUser = \OCP\USER::getUser();
}

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
$objectTree = new Share_ObjectTree();
//$objectTree = new \OC\Connector\Sabre\ObjectTree();

//$server = new Sabre_DAV_Server($rootDir);
$server = new OC_Connector_Sabre_Server_chooser($objectTree);

$requestBackend = new OC_Connector_Sabre_Request();
$server->httpRequest = $requestBackend;

// Path
//$baseuri = OC_App::getAppWebPath('chooser').'appinfo/remote.php';
$baseuri = OC::$WEBROOT."/remote.php/mydav";
// Known aliases
if(strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/files/")===0){
	$baseuri = OC::$WEBROOT."/files";
}
elseif(strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/public/")===0){
	$baseuri = OC::$WEBROOT."/public";
}
elseif(strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/sharingin/")===0){
	$baseuri = OC::$WEBROOT."/sharingin";
}
elseif(strpos($_SERVER['REQUEST_URI'], OC::$WEBROOT."/group/")===0){
	$group = preg_replace("|^".OC::$WEBROOT."/group/|", "", $_SERVER['REQUEST_URI']);
	$group = preg_replace("|/.*$|", "", $group);
	$baseuri = OC::$WEBROOT."/group/".$group;
}
$server->setBaseUri($baseuri);

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

//$authBackend = new OC_Connector_Sabre_Auth();
$authBackend = new OC_Connector_Sabre_Auth_NBF();
$authPlugin = new Sabre\DAV\Auth\Plugin($authBackend, $name);
$server->addPlugin($authPlugin);

//if(strpos($_SERVER['REQUEST_URI'], "/files/")!==0){
if($baseuri == OC::$WEBROOT."/public"){
	//OC_Log::write('chooser','REQUEST '.$_SERVER['REQUEST_URI'], OC_Log::WARN);
	$authBackendShare = new Sabre\DAV\Auth\Backend\Share($baseuri);
	$authPluginShare = new Sabre\DAV\Auth\Plugin($authBackendShare, $name);
	$server->addPlugin($authPluginShare);

	if($authBackendShare->path!==null){
		//$_SERVER['REQUEST_URI'] = $baseuri."/".$authBackendShare->path;
		//$server->setBaseUri($baseuri."/".$authBackendShare->token);
		$objectTree->auth_token = $authBackendShare->token;
		$objectTree->auth_path = $authBackendShare->path;
		$objectTree->allowUpload = $authBackendShare->allowUpload;
	}
}
elseif($baseuri == OC::$WEBROOT."/sharingin"){
	$objectTree->allowUpload = false;
}

// Also make sure there is a 'data' directory, writable by the server. This directory is used to store information about locks
$lockBackend = new OC_Connector_Sabre_Locks();
$lockPlugin = new Sabre\DAV\Locks\Plugin($lockBackend);
$server->addPlugin($lockPlugin);

$server->addPlugin(new \Sabre\DAV\Browser\Plugin(false)); // Show something in the Browser, but no upload

$server->addPlugin(new OC_Connector_Sabre_FilesPlugin());
//$server->addPlugin(new OC_Connector_Sabre_AbortedUploadDetectionPlugin());

$server->addPlugin(new OC_Connector_Sabre_MaintenancePlugin());
$server->addPlugin(new OC_Connector_Sabre_ExceptionLoggerPlugin('davs'));

// Accept mod_rewrite internal redirects.
$_SERVER['REQUEST_URI'] = preg_replace("|^".OC::$WEBROOT."/*remote.php/webdav|",
		OC::$WEBROOT."/remote.php/mydav/", $_SERVER['REQUEST_URI']);
// Accept include by remote.php from files_sharding.
$_SERVER['REQUEST_URI'] = preg_replace("|^".OC::$WEBROOT."/*remote.php/davs|",
		OC::$WEBROOT."/remote.php/mydav/", $_SERVER['REQUEST_URI']);
//$_SERVER['REQUEST_URI'] = preg_replace("/^\/files/", "/remote.php/mydav/", $_SERVER['REQUEST_URI']);
//OC_Log::write('chooser','REQUEST '.serialize($_SERVER), OC_Log::WARN);
//OC_Log::write('chooser','user '.$authPlugin->getCurrentUser(), OC_Log::WARN);

if(!empty($_SERVER['BASE_URI'])){
	// Accept include from remote.php from other apps and set root accordingly
	$server->setBaseUri($_SERVER['BASE_URI']);
}

// In the case of a move request, a header will contain the destination
// with hard-wired host name. Change this host name on redirect.
if(!empty($_SERVER['HTTP_DESTINATION'])){
	$_SERVER['HTTP_DESTINATION'] = preg_replace("|^".OC::$WEBROOT."/*remote.php/webdav|",
			OC::$WEBROOT."/remote.php/mydav/", $_SERVER['HTTP_DESTINATION']);
	// Accept include by remote.php from files_sharding.
	$_SERVER['HTTP_DESTINATION'] = preg_replace("|^".OC::$WEBROOT."/*remote.php/davs|",
			OC::$WEBROOT."/remote.php/mydav/", $_SERVER['HTTP_DESTINATION']);
}

// wait with registering these until auth is handled and the filesystem is setup
$server->subscribeEvent('beforeMethod', function () use ($server, $objectTree) {
	
	if(!empty($_SERVER['BASE_DIR'])){
		OC_Log::write('chooser','Non-files access: '.$_SERVER['BASE_DIR'], OC_Log::WARN);
		\OC\Files\Filesystem::tearDown();
		\OC\Files\Filesystem::init($_SERVER['PHP_AUTH_USER'], $_SERVER['BASE_DIR']);
		$view = new \OC\Files\View($_SERVER['BASE_DIR']);
	}
	elseif(empty($group)){
		$view = \OC\Files\Filesystem::getView();
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
}, 30); // priority 30: after auth (10) and acl(20), before lock(50) and handling the request

require_once('apps/chooser/appinfo/apache_note_user.php');

$ok = true;
if(\OCP\App::isEnabled('files_sharding')){
	$userServerAccess = \OCA\FilesSharding\Lib::getUserServerAccess();
	// Block all access if account is locked on server
	if(\OCP\App::isEnabled('files_sharding') &&
		$userServerAccess!=\OCA\FilesSharding\Lib::$USER_ACCESS_ALL &&
		$userServerAccess!=\OCA\FilesSharding\Lib::$USER_ACCESS_READ_ONLY){
		$ok = false;
	}
}

// Block write operations on r/o server
if(\OCP\App::isEnabled('files_sharding') &&
		$userServerAccess==\OCA\FilesSharding\Lib::$USER_ACCESS_READ_ONLY &&
		(strtolower($_SERVER['REQUEST_METHOD'])=='mkcol' || strtolower($_SERVER['REQUEST_METHOD'])=='put' ||
		strtolower($_SERVER['REQUEST_METHOD'])=='move' || strtolower($_SERVER['REQUEST_METHOD'])=='delete' ||
		strtolower($_SERVER['REQUEST_METHOD'])=='proppatch')){
	$ok = false;
}

// And off we go!
if($ok){
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
elseif(session_status()===PHP_SESSION_ACTIVE){
	session_destroy();
	$session_id = session_id();
	unset($_COOKIE[$session_id]);
}

