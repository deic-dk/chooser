<?php

// This is for apache to use for logging.
// Like e.g.
// LogFormat "%h %l %u %{username}n %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\"" combined
if(\OC_User::isLoggedIn()){
	apache_note( 'username', \OC_User::getUser() );
}
elseif(!empty($_SERVER['PHP_AUTH_USER'])){
	apache_note( 'username', $_SERVER['PHP_AUTH_USER'] );
}