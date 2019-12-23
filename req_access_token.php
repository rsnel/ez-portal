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

/* so, let's see if it works */

$tokeninfo = zportal_GET_row('tokens/~current');

$user = dereference($tokeninfo, 'user');

$expires = dereference($tokeninfo, 'expires');

set_access_token_cookie($expires, $access_token);

$userinfo = zportal_GET_row('users/~me');

/* add info to database */

$code = dereference($userinfo, 'code');

if ($code != $user) fatal("user from tokens/~current should match code from users/~me, but it doesn't");

$isStudent = dereference($userinfo, 'isStudent');
$isEmployee = dereference($userinfo, 'isEmployee');
$isFamilyMember = dereference($userinfo, 'isFamilyMember');

db_exec(<<<EOQ
INSERT INTO access ( access_token, access_code, access_isStudent,
	access_isEmployee, access_isFamilyMember )
VALUES ( ?, ?, ?, ?, ? )
EOQ
, $access_token, $code, $isStudent, $isEmployee, $isFamilyMember);

header('Location: https://'.$_SERVER['HTTP_HOST'].'/');

?>
