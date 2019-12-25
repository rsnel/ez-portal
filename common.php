<?php

$config_defaults = array(
	'STRIP_CATEGORIE_OF_STAMKLAS' => 'false',
	'MAX_LESUUR' => '9',
	'NAMES_BUG' => 'none',
	'TIMEZONE' => 'Europe/Amsterdam'
);

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

function get_config() {
	global $config_defaults;

	$config_local = db_all_assoc_rekey("SELECT config_key, config_value FROM config");

	foreach ($config_defaults as $key => $value) {
		if (!array_key_exists($key, $config_local)) {
			$config_local[$key] = $value;
			db_exec("INSERT INTO config ( config_key, config_value ) VALUES ( ?, ? )", $key, $value);
		}
	}

	return $config_local;
}

function config($key) {
	static $config_static;

	if (!isset($config_static)) $config_static = get_config();

	if (!isset($config_static[$key])) fatal("config key $key not set?!?!");

	return $config_static[$key];
}

date_default_timezone_set(config('TIMEZONE'));

function get_sisyinfo() {
	$sisyinfo = db_single_row("SELECT * FROM sisys WHERE sisy_zid = ?", config('SISY'));
	if ($sisyinfo == NULL || dereference($sisyinfo, 'sisy_archived')) fatal('geconfigureerd schooljaar bestaat niet of is gearchiveerd');

	return $sisyinfo;
}

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

function htmlenc($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
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

function set_random_token($sisyinfo, $canViewProjectSchedules = 1, $canViewProjectNames = 1) {
	$where = 'TIMESTAMPDIFF(HOUR, NOW(), access_expires) >= 1 AND ( FALSE';

	if (dereference($sisyinfo, 'employeeCanViewProjectSchedules') >= $canViewProjectSchedules)
		$where .= ' OR isEmployee = 1';

	if (dereference($sisyinfo, 'studentCanViewProjectSchedules') >= $canViewProjectSchedules &&
		dereference($sisyinfo, 'studentCanViewProjectNames') >= $canViewProjectNames)
		$where .= ' OR isStudent = 1';

	$where .= ' )';

	$candidates = db_all_assoc_rekey(
		"SELECT * FROM users JOIN access USING (entity_id) WHERE $where");

	if (!count($candidates))
		fatal("no user available with sufficient permissions to do anything meaningful");

	$candidate = $candidates[array_rand($candidates)];

	zportal_set_access_token(dereference($candidate, 'access_token'));

	/* verify if the token still works (fatal error when not...) */
	$tokeninfo = zportal_GET_row("tokens/~current");

	db_exec("UPDATE access SET access_refreshed = NOW() WHERE access_id = ?", dereference($candidate, 'access_id'));
}

function db_get_entity_id($entity_name, $entity_type) {
	if ($entity_type != 'PERSOON' &&
		$entity_type != 'LOKAAL' &&
		$entity_type != 'LESGROEP' &&
		$entity_type != 'STAMKLAS' &&
		$entity_type != 'VAK' &&
		$entity_type != 'CATEGORIE') fatal("invalid value for entity_type ($entity_type)");

	return db_get_id('entity_id', 'entities',
                'entity_name', $entity_name, 'entity_type', $entity_type);
}

function update_user($userinfo) {
	$entity_name = htmlenc(dereference($userinfo, 'code'));

	$entity_id = db_get_id('entity_id', 'entities',
		'entity_name', $entity_name, 'entity_type', 'PERSOON');

	$user_id = db_get_id('user_id', 'users', 'entity_id', $entity_id);

	echo("entity_name=$entity_name, entity_id=$entity_id, user_id=$user_id\n");

	$lastName = dereference($userinfo, 'lastName');
	if (config('NAMES_BUG') == 'ovc') {
		$lastName = (explode(',', $lastName))[0];
	}
	db_exec(<<<EOQ
UPDATE users SET firstName = ?, prefix = ?, lastName = ?,
	isStudent = ?, isEmployee = ?, isFamilymember = ?,
	isSchoolScheduler = ?, isSchoolLeader = ?, isStudentAdministrator = ?,
	isTeamLeader = ?, isSectionLeader = ?, isMentor = ?,
	isParentTeacherNightScheduler = ?, isDean = ? WHERE user_id = $user_id
EOQ
	, htmlenc(dereference($userinfo, 'firstName')),
	htmlenc(dereference($userinfo, 'prefix')), htmlenc($lastName),
	dereference($userinfo, 'isStudent'),
	dereference($userinfo, 'isEmployee'),
	dereference($userinfo, 'isFamilyMember'),
	dereference($userinfo, 'isSchoolScheduler'),
	dereference($userinfo, 'isSchoolLeader'),
	dereference($userinfo, 'isStudentAdministrator'),
	dereference($userinfo, 'isTeamLeader'),
	dereference($userinfo, 'isSectionLeader'),
	dereference($userinfo, 'isMentor'),
	dereference($userinfo, 'isParentTeacherNightScheduler'),
	dereference($userinfo, 'isDean'));
}

function update_users() {
	$userinfos = zportal_GET_data('users', 'schoolInSchoolYear', config('SISY'));

	foreach ($userinfos as $userinfo) {
	        update_user($userinfo);
	}
}

function update_sisy_function($function) {
	$sisy_zid = dereference($function, 'schoolInSchoolYear');
	$sisy_id = db_get_id('sisy_id', 'sisys', 'sisy_zid', $sisy_zid);

	db_exec(<<<EOQ
UPDATE sisys
SET sisy_name = ?, studentCanViewOwnSchedule = ?,
	studentCanViewProjectSchedules = ?, studentCanViewProjectNames = ?,
	employeeCanViewOwnSchedule = ?, employeeCanViewProjectSchedules = ?
WHERE sisy_id = $sisy_id
EOQ
		,htmlenc(dereference($function, 'schoolInSchoolYearName')),
		dereference($function, 'studentCanViewOwnSchedule'),
		dereference($function, 'studentCanViewProjectSchedules'),
		dereference($function, 'studentCanViewProjectNames'),
		dereference($function, 'employeeCanViewOwnSchedule'),
		dereference($function, 'employeeCanViewProjectSchedules'));
	
}

function update_sisy($sisyinfo) {
	$sisy_zid = dereference($sisyinfo, 'id');
	$sisy_id = db_get_id('sisy_id', 'sisys', 'sisy_zid', $sisy_zid);

	db_exec(<<<EOQ
UPDATE sisys SET sisy_year = ?, sisy_name = ?, sisy_archived = ? WHERE sisy_id = $sisy_id
EOQ
		, dereference($sisyinfo, 'year'),
		htmlenc(dereference($sisyinfo, 'name')),
		dereference($sisyinfo, 'archived'));
	
}

function update_sisys() {
	$functions = zportal_GET_data('schoolfunctionsettings');
	foreach ($functions as $function) {
		update_sisy_function($function);
	}

	$sisyinfos = zportal_GET_data('schoolsinschoolyears');

	foreach ($sisyinfos as $sisyinfo) {
		update_sisy($sisyinfo);
	}

}

function update_holiday($holiday) {
	$holiday_zid = dereference($holiday, 'id');
	$sisy_zid = dereference($holiday, 'schoolInSchoolYear');
	$sisy_id = db_get_id('sisy_id', 'sisys', 'sisy_zid', $sisy_zid);
	$holiday_id = db_get_id('holiday_id', 'holidays', 'holiday_zid', $holiday_zid);

	db_exec(<<<EOQ
UPDATE holidays
SET sisy_id = $sisy_id, holiday_name = ?, holiday_start = ?, holiday_end = ?
WHERE holiday_id = $holiday_id
EOQ
	, dereference($holiday, 'name'),
	dereference($holiday, 'start'),
	dereference($holiday, 'end'));
}

function update_holidays() {
	$holidays = zportal_GET_data('holidays');
	foreach ($holidays as $holiday) {
		update_holiday($holiday);
	}

}

function update_categories() {
	$categories = zportal_GET_data('departmentsofbranches', 'schoolInSchoolYear', config('SISY'), 'fields', 'id,code');
	foreach ($categories as $categorie) {
		$entity_id = db_get_entity_id(dereference($categorie, 'code'), 'CATEGORIE');
		db_exec("UPDATE entities SET entity_zid = ? WHERE entity_id = ?",
			dereference($categorie, 'id'), $entity_id);
	}
}

function update_groups() {
	$departments = db_single_field("SELECT GROUP_CONCAT(entity_zid) FROM entities WHERE entity_type = 'CATEGORIE'");
	$groups = zportal_GET_data('groupindepartments', 'departmentOfBranch', $departments);
	foreach ($groups as $group) {
		$isMainGroup = dereference($group, 'isMainGroup');
		if ($isMainGroup && config('STRIP_CATEGORIE_OF_STAMKLAS'))
			$entity_name = dereference($group, 'name');
		else $entity_name = dereference($group, 'extendedName');

		$entity_id = db_get_entity_id($entity_name, $isMainGroup?'STAMKLAS':'LESGROEP');
		db_exec("UPDATE entities SET entity_zid = ? WHERE entity_id = ?",
			dereference($group, 'id'), $entity_id);
	}
}

function update_rooms() {
	$sisy_id = db_get_id('sisy_id', 'sisys', 'sisy_zid', config('SISY'));
	$bos_zid = db_single_field('SELECT bos_zid FROM boss WHERE sisy_id = ?', $sisy_id);
	$rooms = zportal_GET_data('locationofbranches', 'branch', $bos_zid, 'fields', 'id,name');
	foreach ($rooms as $room) {
		$entity_id = db_get_entity_id(dereference($room, 'name'), 'LOKAAL');
		db_exec("UPDATE entities SET entity_zid = ? WHERE entity_id = ?",
			dereference($room, 'id'), $entity_id);
	}
}

function update_boss() {
	$boss = zportal_GET_data('branchesofschools');
	foreach ($boss as $bos) {
		$sisy_id = db_get_id('sisy_id', 'sisys',
			'sisy_zid', dereference($bos, 'schoolInSchoolYear'));
		$bos_id = db_get_id('bos_id', 'boss', 'bos_zid', dereference($bos, 'id'));
		db_exec("UPDATE boss SET sisy_id = ?, bos_name = ? WHERE bos_id = ?",
			$sisy_id, htmlenc(dereference($bos, 'name')), $bos_id);
	}
}

/* this function checks if we got a known access_token from the user */
function get_access_info() {
	if (isset($_COOKIE['access_token'])) {
		$access_token = $_COOKIE['access_token'];

		/* do we know this token? */
		$access_info = db_single_row("SELECT * FROM access JOIN entities USING (entity_id) WHERE access_token = ?",
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

function update_weeks($sisyinfo) {
	// we assume that sisy_year is the starting calender year of the schoolyear
	// and that the week of the first thursday of august is the first week 
	// of the schoolyear (which will always be in the summer holidays)
	// the last week of a schoolyear is either (whichever comes first)
	// - the week with a weeknumber one below the starting week
	// - the last week that has thursday in july (does this happen?)
	$august = array();

	for ($i = 1; $i <= 7; $i) {
		$august[$i] = mktime(0, 0, 0, 8, 1, $sisyinfo['sisy_year']);
		if (date("N", $august[$i]) == 4) {
			break;
		}
	}

	//echo("donderdag is op $i augustus, dus in week ".date("W", $august[$i])."\n");
	$thursday = $august[$i];
	$startweek = date("W", $thursday);
	$year = $sisyinfo['sisy_year'];
	$weken = array();

	do {
		if ($year > $sisyinfo['sisy_year'] && date('n', $thursday) > 7) break;
		if ($year == $sisyinfo['sisy_year'] && date("W", $thursday) < $startweek) $year++;
		if ($year > $sisyinfo['sisy_year'] && date("W", $thursday) == $startweek) break;
		$first = strtotime('-3 days', $thursday);
		$last = strtotime('+3 days', $thursday);
		//echo("week ".date("W", $thursday).' in '.$year.' from '.
		//	date('D c', $first).' to '.date('D c', $last)."\n");
		$status = array();
		$free = 0;
		for ($i = 0; $i < 5; $i++) {
			$dayofweek = strtotime("+$i days", $first);
			$check = date('Ymd', $dayofweek);
			$month = date('n', $dayofweek);
			$vakantie = db_single_field("SELECT GROUP_CONCAT(holiday_name) FROM holidays WHERE sisy_id = ? AND holiday_start <= ? AND holiday_end >= ?", $sisyinfo['sisy_id'], $check, $check);
			if ($vakantie || ($year == $sisyinfo['sisy_year'] && $month < 8) || ($year != $sisyinfo['sisy_year'] && $month > 7)) {
				$status[$i] = 0;
				$free++;
			} else $status[$i] = 1;
			//echo("$dayofweek $first ".date('Ymd', $dayofweek)."\n");
			//echo(db_single_field("SELECT GROUP_CONCAT(holiday_name) FROM holidays WHERE sisy_id = ? AND holiday_start <= ? AND holiday_end >= ?", $sisyinfo['sisy_id'], $check, $check)."\n");
		}
		if ($free < 5) {
			$weken[] = array ( 'year' => $year, 'week' => date("W", $thursday), 'monday' => $first, 'ma' => $status[0], 'di' => $status[1], 'wo' => $status[2], 'do' => $status[3], 'vr' => $status[4]);
			$week_id = db_get_id('week_id', 'weeks', 'year', $year, 'week', date("W", $thursday));
			db_exec("UPDATE weeks SET sisy_id = ?, monday_unix_timestamp = ?, ma = ?, di = ?, wo = ?, do = ?, vr = ? WHERE week_id = ?", $sisyinfo['sisy_id'], $first, $status[0], $status[1], $status[2], $status[3], $status[4], $week_id);
		}
		//echo($thursday."\n");
		$thursday = strtotime('+1 week', $thursday);
		//echo($thursday."\n");
	//	exit();
	} while(1);
}

function isodayname($day_number) {
	static $days = array ( 1 => 'ma', 2 => 'di', 3 => 'wo', 4 => 'do', 5 => 'vr');
	return dereference($days, $day_number);
}

// warn when there are doubly named things
function check_doubles() {
	$doubles = db_all_assoc_rekey(<<<EOQ
SELECT entity_name, COUNT(entity_id) clash FROM entities GROUP BY entity_name HAVING clash > 1
EOQ
	);

	if (count($doubles) > 0) {
		echo("warming, some names appear double in entities list, see here:\n");
		print_r($doubles);
	}
}
?>
