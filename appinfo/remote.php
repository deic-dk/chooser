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

OC_Log::write('chooser','Remote access',OC_Log::INFO);

require_once 'chooser/lib/ip_auth.php';
require_once 'chooser/lib/share_auth.php';
require_once 'chooser/lib/server.php';
require_once 'chooser/lib/share_objecttree.php';

OC_App::loadApps(array('filesystem','authentication'));

OCP\App::checkAppEnabled('chooser');

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
$rootDir = new OC_Connector_Sabre_Directory('');
//$objectTree = new \OC\Connector\Sabre\ObjectTree($rootDir);
$objectTree = new Share_ObjectTree($rootDir);

//$server = new Sabre_DAV_Server($rootDir);
$server = new OC_Connector_Sabre_Server_chooser($objectTree);

$requestBackend = new OC_Connector_Sabre_Request();
$server->httpRequest = $requestBackend;

// Path
//$baseuri = OC_App::getAppWebPath('chooser').'appinfo/remote.php';
$baseuri = "/remote.php/mydav";
// Known aliases
if(strpos($_SERVER['REQUEST_URI'], "/files/")===0){
	$baseuri = "/files";
}
if(strpos($_SERVER['REQUEST_URI'], "/public/")===0){
	$baseuri = "/public";
}
$server->setBaseUri($baseuri);

// Auth backends
$defaults = new OC_Defaults();
$name = $defaults->getName();

//$_SERVER['REQUEST_URI'] = preg_replace("/^\/public/", "/remote.php/mydav/", $_SERVER['REQUEST_URI']);

$authBackendIP = new OC_Connector_Sabre_Auth_ip_auth();
$authPluginIP = new Sabre_DAV_Auth_Plugin($authBackendIP, $name);//should use $validTokens here
$server->addPlugin($authPluginIP);

$authBackend = new OC_Connector_Sabre_Auth();
$authPlugin = new Sabre_DAV_Auth_Plugin($authBackend, $name);
$server->addPlugin($authPlugin);

if(strpos($_SERVER['REQUEST_URI'], "/files/")!==0){
	//OC_Log::write('chooser','REQUEST: '.$_SERVER['REQUEST_URI']." : ".$_SERVER['REMOTE_ADDR'], OC_Log::WARN);
	$authBackendShare = new OC_Connector_Sabre_Auth_share_auth($baseuri);
	$authPluginShare = new Sabre_DAV_Auth_Plugin($authBackendShare, $name);
	$server->addPlugin($authPluginShare);

	if($authBackendShare->path!==null){
		//$_SERVER['REQUEST_URI'] = $baseuri."/".$authBackendShare->path;
		//$server->setBaseUri($baseuri."/".$authBackendShare->token);
		$objectTree->auth_token = $authBackendShare->token;
		$objectTree->auth_path = $authBackendShare->path;
		$objectTree->allowUpload = $authBackendShare->allowUpload;
	}
}

// Also make sure there is a 'data' directory, writable by the server. This directory is used to store information about locks
$lockBackend = new OC_Connector_Sabre_Locks();
$lockPlugin = new Sabre_DAV_Locks_Plugin($lockBackend);
$server->addPlugin($lockPlugin);

$server->addPlugin(new OC_Connector_Sabre_FilesPlugin());
$server->addPlugin(new OC_Connector_Sabre_AbortedUploadDetectionPlugin());

$server->addPlugin(new OC_Connector_Sabre_QuotaPlugin());
$server->addPlugin(new OC_Connector_Sabre_MaintenancePlugin());

// Accept mod_rewrite internal redirects
$_SERVER['REQUEST_URI'] = preg_replace("/^\/remote.php\/webdav/", "/remote.php/mydav", $_SERVER['REQUEST_URI']);
//$_SERVER['REQUEST_URI'] = preg_replace("/^\/files/", "/remote.php/mydav/", $_SERVER['REQUEST_URI']);
//OC_Log::write('chooser','REQUEST '.serialize($_SERVER), OC_Log::WARN);
//OC_Log::write('chooser','user '.$authPlugin->getCurrentUser(), OC_Log::WARN);
	//OC_Log::write('chooser','REQUEST: '.$_SERVER['REQUEST_URI']." : ".$_SERVER['REMOTE_ADDR'], OC_Log::WARN);

// And off we go!
$server->exec();


