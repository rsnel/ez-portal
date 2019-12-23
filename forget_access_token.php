<?php

require_once('common.php');

$access_info = get_access_info();

remove_access_token_cookie();

db_exec("DELETE FROM access WHERE access_id = ?", $access_info['access_id']);

header('Location: https://'.$_SERVER['HTTP_HOST'].'/');

?>
