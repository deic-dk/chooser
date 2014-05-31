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

$_POST['dir'] = urldecode($_POST['dir']);
OC_Log::write('chooser','Listing: '.$_POST['dir'], OC_Log::WARN);
if( $_POST['dir']!= '' && !\OC\Files\Filesystem::file_exists($_POST['dir']) ) {
	exit;
}

$user = \OC_User::getUser();
$files = array();
foreach( \OC\Files\Filesystem::getDirectoryContent( $_POST['dir'] ) as $i ) {
	if(array_key_exists('path', $i) && (basename($i['path']) == '.' || basename($i['path']) == '..')){
		continue;
	}
	$i['date'] = OCP\Util::formatDate($i['mtime'] );
	$i['owner'] = $user;
	OC_Log::write('chooser','Source: '.implode("::", array_keys($i))."-->".implode("::",array_values($i)), OC_Log::WARN);
	$files[] = $i;
}

echo "<ul class=\"jqueryFileTree\" style=\"display: none;\">";
// All dirs
foreach( $files as $file ) {

	$path = $_POST['dir'].$file['name'];
	$path = preg_replace('/^\//', '', $path);
	if(\OC\Files\Filesystem::is_dir($path) || $file['mimetype'] == 'httpd/unix-directory') {
		echo "<li class=\"directory collapsed\"><a href=\"#\" rel=\"" . $path . "/\">" . htmlentities($path) . "</a></li>";
	}
}
// All files
foreach( $files as $file ) {
	$path = $_POST['dir'].$file['name'];
	$path = preg_replace('/^\//', '', $path);
	if(!\OC\Files\Filesystem::is_dir($path) && $file['mimetype'] != 'httpd/unix-directory') {
		$ext = preg_replace('/^.*\./', '', $path);
		echo "<li class=\"file ext_$ext\"><a href=\"#\" rel=\"" . $path . "\">" . htmlentities($path) . "</a></li>";
	}
}
echo "</ul>";


?>