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

\OCP\JSON::checkLoggedIn();
\OCP\JSON::checkAppEnabled('chooser');

$user = \OC_User::getUser();

if(OCP\App::isEnabled('user_group_admin')){
	// Show folders shared via user_group_admin if available
	OC::$CLASSPATH['OC_User_Group_Admin_Backend'] ='apps/user_group_admin/lib/backend.php';
	OC_Group::useBackend(new OC_User_Group_Admin_Backend());
	// Allow browsing group folders
	if(!empty($_POST['group'])){
		\OC\Files\Filesystem::tearDown();
		$groupDir = '/'.$user.'/user_group_admin/'.$_POST['group'];
		\OC\Files\Filesystem::init($user, $groupDir);
	}
}

$_POST['dir'] = urldecode($_POST['dir']);
OC_Log::write('chooser','Listing: '.$_POST['dir'], OC_Log::WARN);
if( $_POST['dir']!= '' && !\OC\Files\Filesystem::file_exists($_POST['dir']) ) {
	exit;
}

$files = array();
foreach( \OC\Files\Filesystem::getDirectoryContent( $_POST['dir'] ) as $i ) {
	if(array_key_exists('path', $i) && (basename($i['path']) == '.' || basename($i['path']) == '..')){
		continue;
	}
	$i['date'] = OCP\Util::formatDate($i['mtime'] );
	$i['owner'] = $user;
	$files[] = $i;
}

// Default to true
$showRoot = empty($_POST['showRoot']) || $_POST['showRoot']!='false' && $_POST['showRoot']!='no';
// Default to true
$showHidden = empty($_POST['showHidden']) || $_POST['showHidden']!='false' && $_POST['showHidden']!='no';
// Default to true
$showFiles = empty($_POST['showFiles']) || $_POST['showFiles']!='false' && $_POST['showFiles']!='no';

echo "<ul class=\"jqueryFileTree\" style=\"display: none;\">";
// All dirs
foreach( $files as $file ) {
	if(!$showHidden && strpos($file['name'], '.')===0){
		continue;
	}
	$path = ($showRoot?rtrim($_POST['dir'], '/'):'').'/'.ltrim($file['name'], '/');
	$path = preg_replace('/^\//', '', $path);
	if(\OC\Files\Filesystem::is_dir($path) || $file['mimetype'] == 'httpd/unix-directory') {
		echo "<li class=\"directory collapsed\"><a href=\"#\" rel=\"" . $path . "/\">" . htmlentities($path) . "</a></li>";
	}
}
// All files
foreach( $files as $file ) {
	if(!$showHidden && strpos($file['name'], '.')===0){
		continue;
	}
	$path = ($showRoot?rtrim($_POST['dir'], '/'):'').'/'.ltrim($file['name'], '/');
	$path = preg_replace('/^\//', '', $path);
	if(!\OC\Files\Filesystem::is_dir($path) && $file['mimetype'] != 'httpd/unix-directory' &&
			$showFiles) {
		$ext = preg_replace('/^.*\./', '', $path);
		echo "<li class=\"file ext_$ext\"><a href=\"#\" rel=\"" . $path . "\">" . htmlentities($path) . "</a></li>";
	}
}
echo "</ul>";


?>
