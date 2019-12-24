<?php

require_once('common.php');
require_once('zportal.php');

$access_info = get_access_info();

if ($access_info) {
	// the user supplied us with a valid auth_token,
	// it makes no sense to request a new one,
	// so we do nothing
	header('Location: https://'.$_SERVER['HTTP_HOST'].'/');
	exit;
}

if (!isset($_POST['code']) || $_POST['code'] === '') fatal("code not set");

// remove whitespace in submitted code
$code = preg_replace('/\s+/', '', $_POST['code']);

// check that te code now consists of 12 digits
if (!preg_match('/^\d{12}$/', $code))
	fatal('code should consist of exactly 12 digits (after the whitespace is removed)');

$json = zportal_POST_json('oauth/token', 'grant_type', 'authorization_code', 'code', $code);

$access_token = dereference($json, 'access_token');

zportal_set_access_token($access_token);

/* so, let's see if the token is accepted by the API */

$tokeninfo = zportal_GET_row('tokens/~current');

$entity_name = dereference($tokeninfo, 'user');

$expires = dereference($tokeninfo, 'expires');

set_access_token_cookie($expires, $access_token);

/* add info to database */
$entity_id = db_get_entity_id($entity_name, 'PERSOON');

db_exec(<<<EOQ
INSERT INTO access ( access_token, entity_id, access_expires ) VALUES ( ?, ?, FROM_UNIXTIME(?) )
EOQ
, $access_token, $entity_id, $expires);

$userinfo = zportal_GET_row('users/~me');

if (dereference($userinfo, 'code') != $entity_name)
	fatal("user from tokens/~current should match code from users/~me, but it doesn't");

/* add name and roles to users table */
update_user($userinfo);

header('Location: https://'.$_SERVER['HTTP_HOST'].'/');

?>
