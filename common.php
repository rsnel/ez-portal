<?php

$config_defaults = array(
	'PORTAL' => '?',
	'STRIP_CATEGORIE_OF_STAMKLAS' => 'true',
	'DOCFILTER' => 'TRUE',
	'MAX_LESUUR' => '1',
	'NAMES_BUG' => 'none',
	'TIMEZONE' => 'Europe/Amsterdam',
	'CAPITALIZE' => 'none',
	'RESPECT_HOLIDAYS' => 'true'
);

// fatal() is for system errors that should not happen during usage
function fatal($string) {
	static $fatal_count = 0;
	if ($fatal_count > 0) exit; // bail out on double error
	$fatal_count++;
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
	global $config;
	static $config_static;

	// is this key in the static config? these
	// values can be accessed without database access
	// and they can't be overwritten by the database
	if (isset($config[$key])) return $config[$key];

	if (!isset($config_static)) $config_static = get_config();

	if (!isset($config_static[$key])) fatal("config key $key not set?!?!");

	return $config_static[$key];
}

if (!is_writable(config('DATADIR')) || !is_readable(config('DATADIR')))
	fatal('datadir '.config('DATADIR').' is not writable or readable');

date_default_timezone_set(config('TIMEZONE'));

function vchecksetarray($base, $keys) {
	if (!is_array($base)) return false;
        foreach ($keys as $key) {
                if (!array_key_exists($key, $base)) return false;
        }

        return true;
}

function dereference($array, $key) {
	if (!is_array($array)) fatal('$array is not an array');
	if (!array_key_exists($key, $array)) fatal("key ->$key<- does not exist in \$array");

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
	setcookie('access_token', $access_token, $expires,
			dirname($_SERVER['PHP_SELF'].'/'),
			$_SERVER['HTTP_HOST'], true, true);
}

function remove_access_token_cookie() {
	// we invalidate the cookie by setting it's expiration
	// time 1 hour in the past, then the browser should
	// forget the cookie
	set_access_token_cookie(time() - 3600);
}

function set_employee_token() {
	$candidates = db_all_assoc_rekey(<<<EOQ
SELECT *
FROM users
JOIN access
USING (entity_id)
WHERE TIMESTAMPDIFF(HOUR, NOW(), access_expires) >= 1
AND isEmployee = 1
EOQ
	);

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
	$entity_name = htmlenc(capitalize(dereference($userinfo, 'code'), 'PERSOON'));

	$entity_id = db_get_entity_id($entity_name, 'PERSOON');

	foreach (dereference($userinfo, 'schoolInSchoolYears') as $sisy_zid) {
		$sisy_id = db_get_id('sisy_id', 'sisys', 'sisy_zid', $sisy_zid);
		db_exec('INSERT IGNORE INTO entity_zids ( entity_id, sisy_id ) VALUES ( ?, ? )',
			$entity_id, $sisy_id);
	}

	if (dereference($userinfo, 'archived'))
		db_exec('UPDATE entities SET entity_visible = 0 WHERE entity_id = ?', $entity_id);

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
	$sisy_zids = get_valid_sisy_zids();
	$userinfos = zportal_GET_data('users', 'schoolInSchoolYear', $sisy_zids);

	foreach ($userinfos as $userinfo) {
	        update_user($userinfo);
	}
}

function update_sisy_function($function) {
	$sisy_zid = dereference($function, 'schoolInSchoolYear');
	$sisy_id = db_get_id('sisy_id', 'sisys', 'sisy_zid', $sisy_zid);

	db_exec(<<<EOQ
UPDATE sisys
SET studentCanViewOwnSchedule = ?,
	studentCanViewProjectSchedules = ?, studentCanViewProjectNames = ?,
	employeeCanViewOwnSchedule = ?, employeeCanViewProjectSchedules = ?
WHERE sisy_id = $sisy_id
EOQ
		, dereference($function, 'studentCanViewOwnSchedule'),
		dereference($function, 'studentCanViewProjectSchedules'),
		dereference($function, 'studentCanViewProjectNames'),
		dereference($function, 'employeeCanViewOwnSchedule'),
		dereference($function, 'employeeCanViewProjectSchedules'));
	
}

function update_sisy($sisyinfo) {
	$sisy_zid = dereference($sisyinfo, 'id');
	$sisy_id = db_get_id('sisy_id', 'sisys', 'sisy_zid', $sisy_zid);

	db_exec(<<<EOQ
UPDATE sisys SET sisy_year = ?, sisy_school = ?, sisy_project = ?, sisy_archived = ? WHERE sisy_id = $sisy_id
EOQ
		, dereference($sisyinfo, 'year'),
		htmlenc(dereference($sisyinfo, 'schoolName')),
		htmlenc(dereference($sisyinfo, 'projectName')),
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
	, htmlenc(dereference($holiday, 'name')),
	dereference($holiday, 'start'),
	dereference($holiday, 'end'));
}

// we will assume that a student or employee has
// access to a sisy when it says so in sisy and
// the sisy is not archived
// (the alternative is to query schoolfunctiontasks,
// but that seems redundant)
function get_valid_sisy_ids($kind = 'employee') {
	return db_single_field(<<<EOQ
SELECT GROUP_CONCAT(sisy_id)
FROM sisys
WHERE sisy_archived = 0
AND ( {$kind}CanViewProjectSchedules OR {$kind}CanViewOwnSchedule )
EOQ
	);
}

function get_valid_sisy_zids($kind = 'employee') {
	return db_single_field(<<<EOQ
SELECT GROUP_CONCAT(sisy_zid)
FROM sisys
WHERE sisy_archived = 0
AND ( {$kind}CanViewProjectSchedules OR {$kind}CanViewOwnSchedule )
EOQ
	);
}

function update_holidays() {
	$holidays = zportal_GET_data('holidays');
	foreach ($holidays as $holiday) {
		update_holiday($holiday);
	}

}

function update_categories() {
	$categories = zportal_GET_data('departmentsofbranches', 'fields', 'id,code,branchOfSchool,schoolInSchoolYearId');
	foreach ($categories as $categorie) {
		$entity_id = db_get_entity_id(capitalize(
			htmlenc(dereference($categorie, 'code')), 'CATEGORIE'), 'CATEGORIE');
		$sisy_id = db_get_id('sisy_id', 'sisys', 'sisy_zid',
			dereference($categorie, 'schoolInSchoolYearId'));
		$bos_id = db_get_id('bos_id', 'boss', 'bos_zid',
			dereference($categorie, 'branchOfSchool'));
		if ($sisy_id != db_single_field('SELECT sisy_id FROM boss WHERE bos_id = ?', $bos_id))
			fatal('portal inconsistent!?!?!');

		db_exec("INSERT IGNORE INTO entity_zids ( entity_id, bos_id, sisy_id, entity_zid ) VALUES ( ?, ?, ?, ? )", $entity_id, $bos_id, $sisy_id, dereference($categorie, 'id'));
	}
}

function update_groups() {
	$groups = zportal_GET_data('groupindepartments');
	foreach ($groups as $group) {
		$isMainGroup = dereference($group, 'isMainGroup');
		if ($isMainGroup && config('STRIP_CATEGORIE_OF_STAMKLAS') == 'true')
			$entity_name = capitalize(htmlenc(dereference($group, 'name')), 'STAMKLAS');
		else $entity_name = capitalize(htmlenc(dereference($group, 'extendedName')), 'LESGROEP');

		$info = db_single_row(<<<EOQ
SELECT *
FROM entity_zids
JOIN entities USING (entity_id)
WHERE entity_type = 'CATEGORIE' AND entity_zid = ?
EOQ
		, dereference($group, 'departmentOfBranch'));
		if (!$info) fatal("categorie ".dereference($group, 'departmentOfBranch')." of $entity_name not found");
		$entity_id = db_get_entity_id($entity_name, $isMainGroup?'STAMKLAS':'LESGROEP');
		db_exec("INSERT IGNORE INTO entity_zids ( entity_id, parent_entity_id, bos_id, sisy_id, entity_zid ) VALUES ( ?, ?, ?, ?, ? )", $entity_id, $info['entity_id'], $info['bos_id'], $info['sisy_id'], dereference($group, 'id'));
	}
}

function recapitalize_lesgroepen() {
	$lesgroepen = db_all_assoc_rekey("SELECT * FROM entities WHERE entity_type = 'LESGROEP'");
	foreach ($lesgroepen as $entity_id => $info) {
		//print_r($info);
		//exit;
		echo($info['entity_name']."\n");
		db_exec('UPDATE entities SET entity_name = ? WHERE entity_id = ?', capitalize($info['entity_name'], 'LESGROEP'), $entity_id);
	}
}

function update_rooms() {
	//$sisy_id = db_get_id('sisy_id', 'sisys', 'sisy_zid', config('SISY'));
	//$bos_zid = db_single_field('SELECT bos_zid FROM boss WHERE sisy_id = ?', $sisy_id);
	$rooms = zportal_GET_data('locationofbranches', 'fields', 'id,name,branchOfSchool,secondaryBranches'); //, 'branch', $bos_zid, 'fields', 'id,name');
	foreach ($rooms as $room) {
		if (count(dereference($room, 'secondaryBranches'))) {
			if (count(dereference($room, 'secondaryBranches')) == 1 && dereference($room, 'secondaryBranches')[0] == dereference($room, 'branchOfSchool')) {
				// no problem
			} else {
				print_r($room);
				fatal('secondaryBranches is niet ondersteund');
			}
		}
		$bos_id = db_get_id('bos_id', 'boss', 'bos_zid',
			dereference($room, 'branchOfSchool'));
		$sisy_id = db_single_field('SELECT sisy_id FROM boss WHERE bos_id = ?', $bos_id);
		if (!$sisy_id) fatal("sisy_id of bos_id=$bos_id is false?!?!");
		$entity_id = db_get_entity_id(capitalize(htmlenc(dereference($room, 'name')), 'LOKAAL'), 'LOKAAL');
		db_exec("INSERT IGNORE INTO entity_zids ( entity_id, bos_id, sisy_id, entity_zid ) VALUES ( ?, ?, ?, ? )", $entity_id, $bos_id, $sisy_id, dereference($room, 'id'));
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
		$access_info = db_single_row("SELECT * FROM access JOIN entities USING (entity_id) JOIN users USING (entity_id) WHERE access_token = ?",
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

function update_weeks() {
	$sisys = db_all_assoc_rekey('SELECT sisy_id, sisy_year FROM sisys');
	foreach ($sisys as $sisy_id => $sisy_year) {
		echo("$sisy_id $sisy_year\n");
		update_weeks_of_sisy($sisy_id, $sisy_year);
	}
}

function update_weeks_of_sisy($sisy_id, $startYear) {
	// we assume that sisy_year is the starting calender year of the schoolyear
	// and that the week of the first thursday of august is the first week 
	// of the schoolyear (which will always be in the summer holidays)
	// the last week of a schoolyear is either (whichever comes first)
	// - the week with a weeknumber one below the starting week
	// - the last week that has thursday in july (does this happen?)
	$august = array();

	for ($i = 1; $i <= 7; $i++) {
		$august[$i] = mktime(0, 0, 0, 8, $i, $startYear);
		if (date("N", $august[$i]) == 4) {
			break;
		}
	}
	print_r($august);

	if (!isset($august[$i])) fatal('impossible');

	//echo("donderdag is op $i augustus, dus in week ".date("W", $august[$i])."\n");
	$thursday = $august[$i];
	$startweek = date("W", $thursday);
	$year = $startYear;
	$weken = array();

	do {
		//echo("year=$year, thursday=$thursday\n");
		if ($year > $startYear && date('n', $thursday) > 7) break;
		if ($year == $startYear && date("W", $thursday) < $startweek) $year++;
		if ($year > $startYear && date("W", $thursday) == $startweek) break;
		$first = strtotime('-3 days', $thursday);
		$last = strtotime('+3 days', $thursday);
		//echo("week ".date("W", $thursday).' in '.$year.' from '.
		//	date('D c', $first).' to '.date('D c', $last)."\n");
		$status = array();
		for ($i = 0; $i < 5; $i++) {
			$dayofweek = strtotime("+$i days", $first);
			$check = date('Ymd', $dayofweek);
			$month = date('n', $dayofweek);
			$vakantie = db_single_field("SELECT GROUP_CONCAT(holiday_name) FROM holidays WHERE sisy_id = ? AND holiday_start <= ? AND holiday_end >= ?", $sisy_id, $check, $check);
			if ($vakantie || ($year == $startYear && $month < 8) || ($year != $startYear && $month > 7)) {
				$status[$i] = 0;
			} else $status[$i] = 1;
		}
		$weken[] = array ( 'year' => $year, 'week' => date("W", $thursday), 'monday' => $first, 'ma' => $status[0], 'di' => $status[1], 'wo' => $status[2], 'do' => $status[3], 'vr' => $status[4]);
		$week_id = db_get_id('week_id', 'weeks', 'year', $year, 'week', date("W", $thursday));
		db_exec("UPDATE weeks SET sisy_id = ?, monday_timestamp = FROM_UNIXTIME(?), ma = ?, di = ?, wo = ?, do = ?, vr = ? WHERE week_id = ?", $sisy_id, $first, $status[0], $status[1], $status[2], $status[3], $status[4], $week_id);
		$thursday = strtotime('+1 week', $thursday);
	} while(1);
}

function isodayname($day_number) {
	static $days = array ( 1 => 'ma', 2 => 'di', 3 => 'wo', 4 => 'do', 5 => 'vr');
	return dereference($days, $day_number);
}

function update_portal_version() {
	$version = zportal_get_json('status/version_name');
	db_exec(<<<EOQ
UPDATE config SET config_value = ? WHERE config_key = 'PORTAL'
EOQ
	, $version);
}

function generate_pairs($week_id, $rooster_version) {
	// delete all pairings that must be generated or that depend on those being generated
	// do it in a specific order to satisfy the foreign key constraint on prev_pair_id
	$do_delete = db_all_assoc('SELECT pair_id FROM pairs WHERE week_id = ? AND rooster_version >= ? ORDER BY pair_id DESC');
	foreach ($do_delete as $pair_id) db_exec('DELETE FROM pairs WHERE pair_id = ?', $pair_id);

	$list = db_all_assoc(<<<EOQ
SELECT log_id id, appointment_id, appointment_instance_zid zid, appointment_valid valid
FROM log
LEFT JOIN (
        SELECT week_id, prev_log_id AS log_id, log_id AS obsolete
        FROM log
        WHERE rooster_version <= ?
) AS next_log USING ( log_id, week_id )
WHERE appointment_id IS NOT NULL AND obsolete IS NULL AND rooster_version <= ? AND week_id = ?
ORDER BY appointment_instance_zid, log_id
EOQ
                , $rooster_version, $rooster_version, $week_id);

        $pairs = db_all_assoc_rekey(<<<EOQ
SELECT log_id, appointment_id, pair_id
FROM pairs
LEFT JOIN (
        SELECT week_id, prev_pair_id pair_id, pair_id obsolete
        FROM pairs
        WHERE rooster_version <= ?
) AS next_pairs USING (pair_id, week_id)
WHERE obsolete IS NULL AND rooster_version <= ? AND week_id = ?
EOQ
                , $rooster_version, $rooster_version, $week_id);

	if (!count($list)) return;

	$b = array_shift($list);

	foreach ($list as $a) {
                if ($a['zid'] == $b['zid']) {
                        echo("match {$a['id']} <-> {$b['id']} ({$a['valid']}{$b['valid']})\n");
                        if (!isset($pairs[$b['id']])) {
                                unset($pairs[$b['id']]);
                                unset($pairs[$a['id']]);
                                db_exec('INSERT INTO pairs ( week_id, rooster_version, log_id, paired_log_id, appointment_id ) VALUES ( ?, ?, ?, ?, ? )', $week_id, $rooster_version, $b['id'], $a['id'], $a['appointment_id']);
                                db_exec('INSERT INTO pairs ( week_id, rooster_version, log_id, paired_log_id, appointment_id ) VALUES ( ?, ?, ?, ?, ? )', $week_id, $rooster_version, $a['id'], $b['id'], $b['appointment_id']);
                        } else if ($pairs[$b['id']]['appointment1_id'] == $a['id']) {
                                // ok, already available
                        } else {
				// soft-overwrite old pair
                                db_exec('INSERT INTO pairs ( prev_pair_id, week_id, rooster_version, log_id, paired_log_id, appointment_id ) VALUES ( ?, ?, ?, ?, ?, ? )', $pairs[$b['id']]['pair_id'], $week_id, $rooster_version, $b['id'], $a['id'], $a['appointment_id']);
                                db_exec('INSERT INTO pairs ( prev_pair_id, week_id, rooster_version, log_id, paired_log_id, appointment_id ) VALUES ( ?, ?, ?, ?, ?, ? )', $pairs[$a['id']]['pair_id'], $week_id, $rooster_version, $a['id'], $b['id'], $b['appointment_id']);
                        }
                }
                $b = $a;
	}

}

function lock_release($type) {
	// we can only release a lock when it is ours, so we check by 
	// including our PID
	$PID = getmypid();
	
	$affected_rows = db_exec('DELETE FROM locking WHERE locking_id = ? AND locking_pid = ?',
		$type, $PID);

	if (!$affected_rows) fatal('tried to delete a non-existing lock or a lock that was not ours');
}

function lock_acquire($type, $string) {
	$PID = getmypid();
	do {
		$lock = db_single_row(<<<EOQ
SELECT UNIX_TIMESTAMP(locking_last_timestamp) timestamp FROM locking WHERE locking_id = ?
EOQ
			, $type);
		if ($lock === NULL) {
			$affected_rows = db_exec(<<<EOQ
INSERT IGNORE INTO locking ( locking_id, locking_pid, locking_status )
VALUES ( ?, ?, ? )
EOQ
				, $type, $PID, $string);

			if ($affected_rows) return 1; // success

			// we've lost the race, retry
		} else {
			// there already is a lock... let's look how old it is
			$age = time() - $lock['timestamp'];
			if ($oud < 120) return 0; // lock is not old enough
			
			// lock is too old to be active, remove it
			// and try to take it
			db_exec('DELETE FROM locking WHERE locking_id = ?', $type);
		}
	} while (1);
}

function lock_renew($type, $string) {
	$PID = getmypid();
	// we can only renew a lock if it exists and if it's ours, so we include
	// our PID and check if something happened (affected rows)
	$affected_rows = db_exec('UPDATE locking SET locking_status = ?, locking_last_timestamp = NOW(6) WHERE locking_id = ? AND locking_pid = ?', $string, $type, $PID);
	if (!$affected_rows) fatal('tried to renew a lock that was not ours...');
}

function show_cancelled($les, $bw) {
	if ($bw == 'b') return false;
	else if ($bw == 'w') return $les['f_s'] == 'cancelled';
}

function show_new($les, $bw) {
	if ($bw == 'b') return false;
	else if ($bw == 'w') return $les['f_s'] == 'new';
}

function show_replaced($les, $bw) {
	if ($bw == 'b') return false;
	else if ($bw == 'w') return $les['f_v'] == 0;
}

function show_replacement($les, $bw) {
	if ($bw == 'b') return false;
	else if ($bw == 'w') return $les['f_v'] == 1 && $les['s_id'] && $les['f_aid'] != $les['s_aid'];
}

function show_normal($les, $bw) {
	if ($bw == 'w') return true;
	else if ($bw == 'b') return ($les['f_s'] != 'new') && ( ( $les['f_s'] == 'cancelled' || $les['f_v'] == 0 ) || ( $les['f_v'] == 1 && !$les['s_id'] ) );
}

function master_query($entity_ids, $kind, $rooster_version, $week_id) {
	if (!$entity_ids) {
		$join = '';
		$where = '';
	} else if ($kind == 'students') {
		$join = "JOIN entities2egrps ON entities2egrps.egrp_id = f_l.students_egrp_id\n";
		$where = " AND entity_id IN ( $entity_ids )";
	} else if ($kind == 'groups' || $kind == 'subjects' ||
			$kind == 'teachers' || $kind == 'locations') {
		$join = "JOIN entities2egrps ON entities2egrps.egrp_id = f_a.{$kind}_egrp_id\n";
		$where = " AND entity_id IN ( $entity_ids )";
	} else fatal("impossible value of \$kind");

	return db_query(<<<EOQ
SELECT f_l.log_id f_id, f_a.appointment_id f_aid, f_l.appointment_instance_zid zid,
	f_a.appointment_day f_d, f_a.appointment_timeSlot f_u, f_l.appointment_valid f_v,
	f_l.appointment_state f_s, f_a.groups f_groups, f_a.subjects f_subjects,
	f_a.teachers f_teachers, f_a.locations f_locations,
	pairs.paired_log_id s_id, s_a.appointment_id s_aid, s_a.appointment_day s_d,
	s_a.appointment_timeSlot s_u, s_a.groups s_groups, s_a.subjects s_subjects,
	s_a.teachers s_teachers, s_a.locations s_locations
FROM log AS f_l
LEFT JOIN (
	SELECT week_id, prev_log_id AS log_id, log_id AS obsolete
	FROM log
	WHERE rooster_version <= ?
) AS next_log USING (log_id, week_id)
JOIN appointments AS f_a USING (appointment_id)
{$join}LEFT JOIN (
	SELECT week_id, log_id, paired_log_id, appointment_id FROM pairs
	LEFT JOIN (
		SELECT week_id, prev_pair_id AS pair_id, pair_id obsolete
		FROM pairs
		WHERE rooster_version <= ?
	) AS next_pairs USING (pair_id, week_id)
	WHERE rooster_version <= ? AND obsolete IS NULL
) AS pairs USING (log_id, week_id)
LEFT JOIN appointments AS s_a ON s_a.appointment_id = pairs.appointment_id
WHERE obsolete IS NULL AND f_l.week_id = ? AND f_l.rooster_version <= ?$where
AND f_a.appointment_timeSlot > 0
ORDER BY f_u, f_d, f_v, CASE f_s WHEN 'cancelled' THEN 0 WHEN 'normal' THEN 1 WHEN 'new' THEN 2 END
EOQ
	, $rooster_version, $rooster_version, $rooster_version, $week_id, $rooster_version);
}

function lln_query($entity_ids, $rooster_version, $week_id) {
	return db_single_field(<<<EOQ
SELECT GROUP_CONCAT(DISTINCT log2students.entity_id)
FROM log
LEFT JOIN (
	SELECT week_id, prev_log_id AS log_id, log_id AS obsolete
	FROM log
	WHERE rooster_version <= ?
) AS next_log USING (log_id, week_id)
JOIN appointments USING (appointment_id)
JOIN entities2egrps AS appointments2groups ON appointments2groups.egrp_id = appointments.groups_egrp_id
JOIN entities2egrps AS log2students ON log2students.egrp_id = log.students_egrp_id
WHERE appointments2groups.entity_id IN ( $entity_ids ) AND rooster_version <= ? AND week_id = ?
EOQ
	, $rooster_version, $rooster_version, $week_id);
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

function capitalize_none($name, $type) {
	return $name;
}

function capitalize_ovc($name, $type) {
	switch ($type) {
	case 'LOKAAL':
	case 'CATEGORIE':
	case 'STAMKLAS':
		return strtoupper($name);
	case 'PERSOON':
		// ouder of ln
		if (preg_match('/v?\d\d\d\d+/', $name)) return strtolower($name);
		//
		if (strlen($name) == 4) return strtoupper($name);
		return strtolower($name);
	case 'LESGROEP': /* werkt ook voor stamklas, als de categorie er niet aan zit... */
		if (!preg_match('/^(.*)\.(.*?)(\d+)?$/', $name, $matches)) return strtoupper($name);
		if (!isset($matches[3])) $matches[3] = '';
		return strtoupper($matches[1]).'.'.capitalize_ovc($matches[2], 'VAK').$matches[3];
	case 'VAK':
		if (!strcasecmp($name, 'wisA')) return 'wisA';
		else if (!strcasecmp($name, 'wisB')) return 'wisB';
		else if (!strcasecmp($name, 'wisC')) return 'wisC';
		else if (!strcasecmp($name, 'wisD')) return 'wisD';
		else return $name;
	default:
		fatal("unknown type $type for capitalize");
	}
}
	
function capitalize($name, $type) {
	return ('capitalize_'.config('CAPITALIZE'))($name, $type);
}

function capitalize_group($name) {
	return capitalize($name, 'LESGROEP');
}

function capitalize_subject($name) {
	return capitalize($name, 'VAK');
}

function capitalize_teacher($name) {
	return capitalize($name, 'PERSOON');
}

function capitalize_location($name) {
	return capitalize($name, 'LOKAAL');
}

function search_on_zid($entity_zid, $type) {
	if ($type == 'location') $where = " AND entity_type = 'LOKAAL'";
	else if ($type == 'group')
	       	$where = " AND ( entity_type = 'STAMKLAS' OR entity_type = 'LESGROEP' )";
	else fatal("impossible request to search_on_zid");

	$out = db_single_field("SELECT entity_name FROM entities JOIN entity_zids USING (entity_id) WHERE entity_zid = ?$where", $entity_zid);
	if (!$out) fatal("entity with entity_zid = $entity_zid not found");
	return $out;
}

function search_location_on_zid($entity_zid) {
	return search_on_zid($entity_zid, 'location');
}

function search_group_on_zid($entity_zid) {
	return search_on_zid($entity_zid, 'group');
}

function search_teacher($entity_name) {
	return db_get_entity_id(capitalize_teacher($entity_name), 'PERSOON');
}

function search_subject($entity_name) {
	return db_get_entity_id('/'.capitalize_subject($entity_name), 'VAK');
}

function search_on_name($entity_name) {
	$out = db_single_field("SELECT entity_id FROM entities WHERE entity_name = ?",
		$entity_name);
	if (!$out) fatal("entity with entity_zid = $entity_name not found");
	return $out;
}

function db_get_text_id($text) {
	if ($text === NULL) $text = '';
	return db_get_id('text_id', 'texts', 'text', $text);
}

function db_get_egrp_id($entities, $search_func) {
	$egrp_id = db_get_id('egrp_id', 'egrps', 'egrp', $entities);

	if ($entities) foreach (array_map($search_func, explode(',', $entities)) as $entity_id)
		db_exec('INSERT IGNORE INTO entities2egrps ( entity_id, egrp_id ) VALUES ( ?, ? )',
                        $entity_id, $egrp_id);

	return $egrp_id;
}
 
function functionalsort($array) {
	sort($array);
	return $array;
}

function get_rooster_type($week_id, $rooster_version) {
	//echo("week_id=$week_id, rooster_version=$rooster_version\n");
	$bool = db_single_field(<<<EOQ
SELECT BIT_OR(appointment_state != 'normal' OR !appointment_valid)
FROM log
LEFT JOIN (
	SELECT prev_log_id AS log_id, log_id AS obsolete
	FROM log
	WHERE week_id = ? AND rooster_version <= ?
) next_log USING (log_id)
WHERE week_id = ? AND rooster_version <= ? AND obsolete IS NULL
EOQ
		, $week_id, $rooster_version, $week_id, $rooster_version);
	if ($bool) return 'week';
	else return 'basis';
}

?>
