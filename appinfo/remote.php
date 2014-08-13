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

OC_Log::write('chooser','Remote access',OC_Log::WARN);

require_once 'chooser/lib/ip_auth.php';
require_once 'chooser/lib/server.php';

OC_App::loadApps(array('filesystem','authentication'));

OCP\App::checkAppEnabled('chooser');

ini_set('default_charset', 'UTF-8');
//ini_set('error_reporting', '');
@ob_clean();

//$baseuri = OC_App::getAppWebPath('chooser').'appinfo/remote.php';
$baseuri = "/remote.php/mydav";
$path = substr(OCP\Util::getRequestUri(), strlen($baseuri));

// only need authentication apps
$RUNTIME_APPTYPES=array('authentication');
OC_App::loadApps($RUNTIME_APPTYPES);

//OC_Util::obEnd();

//OC_Util::setupFS($ownCloudUser);

// Create ownCloud Dir
$rootDir = new OC_Connector_Sabre_Directory('');
$objectTree = new \OC\Connector\Sabre\ObjectTree($rootDir);

//$server = new Sabre_DAV_Server($rootDir);
$server = new OC_Connector_Sabre_Server_chooser($objectTree);

$requestBackend = new OC_Connector_Sabre_Request();
$server->httpRequest = $requestBackend;

// Path
$server->setBaseUri($baseuri);

OC_Log::write('chooser','Base URI '.$baseuri,OC_Log::WARN);

// Auth backend
$authBackend = new OC_Connector_Sabre_Auth_ip_auth();

$authBackend1 = new OC_Connector_Sabre_Auth();

$authPlugin = new Sabre_DAV_Auth_Plugin($authBackend,'ownCloud');//should use $validTokens here
$server->addPlugin($authPlugin);

$defaults = new OC_Defaults();
$server->addPlugin(new Sabre_DAV_Auth_Plugin($authBackend1, $defaults->getName()));

// Also make sure there is a 'data' directory, writable by the server. This directory is used to store information about locks
$lockBackend = new OC_Connector_Sabre_Locks();
$lockPlugin = new Sabre_DAV_Locks_Plugin($lockBackend);
$server->addPlugin($lockPlugin);

$server->addPlugin(new OC_Connector_Sabre_QuotaPlugin());
$server->addPlugin(new OC_Connector_Sabre_MaintenancePlugin());

// And off we go!
$server->exec();


