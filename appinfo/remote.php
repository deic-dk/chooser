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
$publicDir = new OC_Connector_Sabre_Directory('');

$server = new Sabre_DAV_Server($publicDir);

$requestBackend = new OC_Connector_Sabre_Request();
$server->httpRequest = $requestBackend;

// Path
$server->setBaseUri($baseuri);

OC_Log::write('chooser','Base URI '.$baseuri,OC_Log::WARN);

// Auth backend
$authBackend = new OC_Connector_Sabre_Auth_ip_auth();

$authPlugin = new Sabre_DAV_Auth_Plugin($authBackend,'ownCloud');//should use $validTokens here
$server->addPlugin($authPlugin);

// Also make sure there is a 'data' directory, writable by the server. This directory is used to store information about locks
$lockBackend = new OC_Connector_Sabre_Locks();
$lockPlugin = new Sabre_DAV_Locks_Plugin($lockBackend);
$server->addPlugin($lockPlugin);

$server->addPlugin(new OC_Connector_Sabre_QuotaPlugin());
$server->addPlugin(new OC_Connector_Sabre_MaintenancePlugin());

// And off we go!
$server->exec();


