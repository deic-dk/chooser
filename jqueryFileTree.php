<?php
//
// jQuery File Tree PHP Connector
//
// Version 1.01-ownCloud-chooser
//
// Cory S.N. LaViska
// A Beautiful Site (http://abeautifulsite.net/)
// 24 March 2008
//
// History:
//
// 1.01 - updated to work with foreign characters in directory/file names (12 April 2008)
// 1.00 - released (24 March 2008)
// 1.01-ownCloud-chooser - heavily modified for use with ownCloud. Frederik Orellana, September 2013.
//
// Output a list of files for jQuery File Tree
//

$user = OCP\USER::getUser();
if(empty($user)){
	$user = \OC_Chooser::checkIP();
	OC_Log::write('chooser', 'Checking user: '.$user, OC_Log::WARN);
	if(!empty($user)){
		$userPrivateServer = \OCA\FilesSharding\Lib::getServerForUser($user, true);
		OC_Log::write('chooser', 'Checking host: '.$user.':'.$_SERVER['HTTP_HOST'].':'.$_SERVER['SERVER_NAME'].':'.
				$userPrivateServer, OC_Log::WARN);
		$userServer = \OC_Chooser::privateToUserVlan($userPrivateServer);
		if(!empty($userServer) && !\OCA\FilesSharding\Lib::isServerMe($userServer)){
			\OC_Response::redirect(rtrim($userServer, '/').'/'.ltrim($_SERVER['REQUEST_URI'], '/'));
			exit;
		}
		else{
			\OC\Files\Filesystem::init($user, '/'.$user.'/files');
		}
	}
}
if(empty($user) /*|| $user!=$allowedQueryUser*/){
	http_response_code(401);
	exit;
}

//\OCP\JSON::checkLoggedIn();
\OCP\JSON::checkAppEnabled('chooser');

//$user = \OC_User::getUser();

if(OCP\App::isEnabled('user_group_admin')){
	// Show folders shared via user_group_admin if available
	OC::$CLASSPATH['OC_User_Group_Admin_Backend'] ='apps/user_group_admin/lib/backend.php';
	OC_Group::useBackend(new OC_User_Group_Admin_Backend());
	// Allow browsing group folders
	if(!empty($_REQUEST['group'])){
		\OC\Files\Filesystem::tearDown();
		$groupDir = '/'.$user.'/user_group_admin/'.$_REQUEST['group'];
		\OC\Files\Filesystem::init($user, $groupDir);
	}
}

//$_REQUEST['dir'] = urldecode($_REQUEST['dir']);
OC_Log::write('chooser','Listing: '.$user.':'.$_REQUEST['dir'], OC_Log::WARN);
if( $_REQUEST['dir']!= '' && !\OC\Files\Filesystem::file_exists($_REQUEST['dir']) ) {
	OC_Log::write('chooser','Directory does not exist: '.$_REQUEST['dir'], OC_Log::ERROR);
	exit;
}

$files = array();
foreach( \OC\Files\Filesystem::getDirectoryContent( $_REQUEST['dir'] ) as $i ) {
	if(array_key_exists('path', $i) && (basename($i['path']) == '.' || basename($i['path']) == '..')){
		continue;
	}
	$i['date'] = OCP\Util::formatDate($i['mtime'] );
	$i['owner'] = $user;
	$files[] = $i;
}

// Default to true
$showRoot = empty($_REQUEST['showRoot']) || $_REQUEST['showRoot']!='false' && $_REQUEST['showRoot']!='no';
// Default to true
$showHidden = empty($_REQUEST['showHidden']) || $_REQUEST['showHidden']!='false' && $_REQUEST['showHidden']!='no';
// Default to true
$showFiles = empty($_REQUEST['showFiles']) || $_REQUEST['showFiles']!='false' && $_REQUEST['showFiles']!='no';
// Default to false
$deleteIcons =!empty($_REQUEST['deleteIcons']) && ($_REQUEST['deleteIcons']=='true' || $_REQUEST['deleteIcons']=='yes');

echo "<ul class=\"jqueryFileTree\" style=\"display: none;\">";
// All dirs
foreach( $files as $file ) {
	if(!$showHidden && strpos($file['name'], '.')===0){
		continue;
	}
	$path = rtrim($_REQUEST['dir'], '/').'/'.ltrim($file['name'], '/');
	$showPath = ($showRoot?rtrim($_REQUEST['dir'], '/'):'').'/'.ltrim($file['name'], '/');
	$path = preg_replace('/^\//', '', $path);
	$showPath = preg_replace('/^\//', '', $showPath);
	if(\OC\Files\Filesystem::is_dir($path) || $file['mimetype'] == 'httpd/unix-directory') {
		echo "<li class=\"directory collapsed\"><a href=\"#\" rel=\"" . $path . "/\">" .
			htmlentities($showPath) .
			"<span class=\"icon-angle-right expand_folder\"></span>" .
			($deleteIcons?"<span class=\"icon-cancel-circled delete_folder\"></span>":"")."</a>".
		"</li>";
	}
}
// All files
foreach( $files as $file ) {
	if(!$showHidden && strpos($file['name'], '.')===0){
		continue;
	}
	$path = rtrim($_REQUEST['dir'], '/').'/'.ltrim($file['name'], '/');
	$showPath = ($showRoot?rtrim($_REQUEST['dir'], '/'):'').'/'.ltrim($file['name'], '/');
	$path = preg_replace('/^\//', '', $path);
	$showPath = preg_replace('/^\//', '', $showPath);
	if(!\OC\Files\Filesystem::is_dir($path) && $file['mimetype'] != 'httpd/unix-directory' &&
			$showFiles) {
		$ext = preg_replace('/^.*\./', '', $path);
		echo "<li class=\"file ext_$ext\"><a href=\"#\" rel=\"" . $path . "\">" . htmlentities($path) . "</a></li>";
	}
}
echo "</ul>";


?>
