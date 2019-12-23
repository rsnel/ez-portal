<?php

// fatal() is for system errors that should not happen during usage
function fatal($string) {
        if (php_sapi_name() != 'cli') header('Content-type: text/plain');
        echo("fatal:$string\n");
        exit;
}

if (!isset($_SERVER['EZ_PORTAL_INSTITUTION']) || !$_SERVER['EZ_PORTAL_INSTITUTION'])
        fatal('environment variable EZ_PORTAL_INSTITUTION not set');

$config_file = dirname(__FILE__).'/config_'.$_SERVER['EZ_PORTAL_INSTITUTION'].'.php';

if (!file_exists($config_file)) fatal('config file '.$config_file.' does not exist');

require_once($config_file);
require_once('db.php');

function vchecksetarray($base, $keys) {
	if (!is_array($base)) return false;
        foreach ($keys as $key) {
                if (!array_key_exists($key, $base)) return false;
        }

        return true;
}

function dereference($array, $key) {
	if (!is_array($array)) fatal('$array is not an array');
	if (!array_key_exists($key, $array)) fatal("key $key does not exist in \$array");

	$args = func_get_args();
        array_shift($args); array_shift($args);

	if (count($args) == 0) return $array[$key];
	else {
		array_unshift($args, $array[$key]);
		return call_user_func_array('dereference', $args);
	}
}

function checksetarray($base) {
	// get args after $base
	$args = func_get_args();
        array_shift($args);

	return vchecksetarray($base, $args);
}

function set_access_token_cookie($expires, $access_token = '') {
	setcookie('access_token', $access_token, $expires, '/',
			$_SERVER['HTTP_HOST'], true, true);
}

function remove_access_token_cookie() {
	// we invalidate the cookie by setting it's expiration
	// time 1 hour in the past, then the browser should
	// forget the cookie
	set_access_token_cookie(time() - 3600);
}

/* this function checks if we got a known access_token from the user */
function get_access_info() {
	if (isset($_COOKIE['access_token'])) {
		$access_token = $_COOKIE['access_token'];

		/* do we know this token? */
		$access_info = db_single_row("SELECT * FROM access WHERE access_token = ?",
				$access_token);

		if ($access_info === NULL) {
			// we don't know this token (anymore), either the user
			// supplied us with a token that we have never seen before
			// or with a token that was deactivated without the
			// corresponding cookie being removed and thusly
			// removed from the database once that was detected

			remove_access_token_cookie();
        	}
		return $access_info;
	} else return NULL;
}

?>
