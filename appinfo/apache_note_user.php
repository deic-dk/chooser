<?php

// This is for apache to use for logging.
if(\OC_User::isLoggedIn()){
	apache_note( 'username', \OC_User::getUser() );
}
elseif(!empty($_SERVER['PHP_AUTH_USER'])){
	apache_note( 'username', $_SERVER['PHP_AUTH_USER'] );
}