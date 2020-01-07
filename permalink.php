<?php

require_once('common.php');
require_once('zportal.php');

$access_id = $_GET['access_id'];
$hash = $_GET['hash'];

$access_info = db_single_row("SELECT *, UNIX_TIMESTAMP(access_expires) expires FROM access WHERE access_id = ?", $access_id);

if (!$access_info)
	fatal("bad permalink");

$hash = md5(config('PERMALINK_SECRET').$access_info['access_token']);
//echo($hash);

if ($hash !== $_GET['hash']) 
	fatal("bad permalink hash");

set_access_token_cookie($access_info['expires'], $access_info['access_token']);

//fatal('cookie set');
header('Location: https://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/');

?>
