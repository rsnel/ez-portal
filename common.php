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
		EOQ, htmlenc(dereference($holiday, 'name')),
		dereference($holiday, 'start'), dereference($holiday, 'end'));
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
		EOQ);
}

function get_valid_sisy_zids($kind = 'employee') {
	return db_single_field(<<<EOQ
		SELECT GROUP_CONCAT(sisy_zid)
		FROM sisys
		WHERE sisy_archived = 0
		AND ( {$kind}CanViewProjectSchedules OR {$kind}CanViewOwnSchedule )
		EOQ);
}

function update_holidays() {
	$holidays = zportal_GET_data('holidays');
	foreach ($holidays as $holiday) {
		update_holiday($holiday);
	}

}

function update_categories() {
	$categories = zportal_GET_data('departmentsofbranches', 'fields',
		'id,code,branchOfSchool,schoolInSchoolYearId');
	foreach ($categories as $categorie) {
		$entity_id = db_get_entity_id(capitalize(
			htmlenc(dereference($categorie, 'code')), 'CATEGORIE'), 'CATEGORIE');
		$sisy_id = db_get_id('sisy_id', 'sisys', 'sisy_zid',
			dereference($categorie, 'schoolInSchoolYearId'));
		$bos_id = db_get_id('bos_id', 'boss', 'bos_zid',
			dereference($categorie, 'branchOfSchool'));
		if ($sisy_id != db_single_field('SELECT sisy_id FROM boss WHERE bos_id = ?', $bos_id))
			fatal('portal inconsistent!?!?!');

		db_exec(<<<EOQ
			INSERT IGNORE INTO entity_zids ( entity_id, bos_id, sisy_id, entity_zid )
			VALUES ( ?, ?, ?, ? )
			EOQ, $entity_id, $bos_id, $sisy_id, dereference($categorie, 'id'));
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
			EOQ, dereference($group, 'departmentOfBranch'));
		if (!$info) fatal("categorie ".dereference($group, 'departmentOfBranch')." of $entity_name not found");
		$entity_id = db_get_entity_id($entity_name, $isMainGroup?'STAMKLAS':'LESGROEP');
		db_exec(<<<EOQ
			INSERT IGNORE INTO entity_zids ( entity_id, parent_entity_id,
				bos_id, sisy_id, entity_zid )
			VALUES ( ?, ?, ?, ?, ? )
			EOQ, $entity_id, $info['entity_id'], $info['bos_id'],
			$info['sisy_id'], dereference($group, 'id'));
	}
}

function recapitalize_lesgroepen() {
	$lesgroepen = db_all_assoc_rekey("SELECT * FROM entities WHERE entity_type = 'LESGROEP'");
	foreach ($lesgroepen as $entity_id => $info) {
		//print_r($info);
		//exit;
		echo($info['entity_name']."\n");
		db_exec('UPDATE entities SET entity_name = ? WHERE entity_id = ?',
			capitalize($info['entity_name'], 'LESGROEP'), $entity_id);
	}
}

function update_rooms() {
	$rooms = zportal_GET_data('locationofbranches', 'fields',
		'id,name,branchOfSchool,secondaryBranches');

	foreach ($rooms as $room) {
		if (count(dereference($room, 'secondaryBranches'))) {
			if (count(dereference($room, 'secondaryBranches')) == 1 &&
				dereference($room, 'secondaryBranches')[0] ==
					dereference($room, 'branchOfSchool')) {
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
		$entity_id = db_get_entity_id(capitalize(htmlenc(
			dereference($room, 'name')), 'LOKAAL'), 'LOKAAL');
		db_exec(<<<EOQ
			INSERT IGNORE INTO entity_zids
				( entity_id, bos_id, sisy_id, entity_zid )
			VALUES ( ?, ?, ?, ? )
			EOQ, $entity_id, $bos_id, $sisy_id, dereference($room, 'id'));
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
		$access_info = db_single_row(<<<EOQ
			SELECT * FROM access
			JOIN entities USING (entity_id)
			JOIN users USING (entity_id)
			WHERE access_token = ?
			EOQ, $access_token);

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
			$vakantie = db_single_field(<<<EOQ
				SELECT GROUP_CONCAT(holiday_name) FROM holidays
				WHERE sisy_id = ? AND holiday_start <= ? AND holiday_end >= ?
				EOQ, $sisy_id, $check, $check);
			if ($vakantie || ($year == $startYear && $month < 8) ||
					($year != $startYear && $month > 7)) {
				$status[$i] = 0;
			} else $status[$i] = 1;
		}
		$weken[] = array (
			'year' => $year, 'week' => date("W", $thursday), 'monday' => $first,
			'ma' => $status[0], 'di' => $status[1], 'wo' => $status[2],
			'do' => $status[3], 'vr' => $status[4]);
		$week_id = db_get_id('week_id', 'weeks', 'year', $year, 'week', date("W", $thursday));
		db_exec(<<<EOQ
			UPDATE weeks SET sisy_id = ?, monday_timestamp = FROM_UNIXTIME(?),
				ma = ?, di = ?, wo = ?, do = ?, vr = ? WHERE week_id = ?
			EOQ, $sisy_id, $first, $status[0], $status[1], $status[2],
			$status[3], $status[4], $week_id);
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
		EOQ, $version);
}

function generate_pairs($week_id, $rooster_version) {
	// delete all pairings that must be generated or that depend on those being generated
	// do it in a specific order to satisfy the foreign key constraint on prev_pair_id
	$do_delete = db_all_assoc(<<<EOQ
		SELECT pair_id
		FROM pairs WHERE week_id = ?
		AND rooster_version >= ?
		ORDER BY pair_id DESC
		EOQ, $week_id, $rooster_version);

	foreach ($do_delete as $pair_id) db_exec('DELETE FROM pairs WHERE pair_id = ?', $pair_id);

	$list = db_all_assoc(<<<EOQ
		SELECT log_id id, appointment_id, appointment_instance_zid zid, appointment_state state 
		FROM log
		LEFT JOIN (
		        SELECT week_id, prev_log_id AS log_id, log_id AS obsolete
		        FROM log
		        WHERE rooster_version <= ?
		) AS next_log USING ( log_id, week_id )
		WHERE appointment_id IS NOT NULL AND obsolete IS NULL
		AND rooster_version <= ? AND week_id = ?
		ORDER BY appointment_instance_zid, log_id
		EOQ, $rooster_version, $rooster_version, $week_id);

        $pairs = db_all_assoc_rekey(<<<EOQ
		SELECT log_id, appointment_id, pair_id
		FROM pairs
		LEFT JOIN (
		        SELECT week_id, prev_pair_id pair_id, pair_id obsolete
		        FROM pairs
		        WHERE rooster_version <= ?
		) AS next_pairs USING (pair_id, week_id)
		WHERE obsolete IS NULL AND rooster_version <= ? AND week_id = ?
		EOQ, $rooster_version, $rooster_version, $week_id);

	if (!count($list)) return;

	$b = array_shift($list);

	foreach ($list as $a) {
                if ($a['zid'] == $b['zid']) {
                        echo("match {$a['id']} <-> {$b['id']} ({$a['state']}-{$b['state']})\n");
                        if (!isset($pairs[$b['id']])) {
                                unset($pairs[$b['id']]);
                                unset($pairs[$a['id']]);
				db_exec(<<<EOQ
					INSERT INTO pairs ( week_id, rooster_version, log_id,
						paired_log_id, appointment_id )
					VALUES ( ?, ?, ?, ?, ? )
					EOQ, $week_id, $rooster_version, $b['id'], $a['id'],
					$a['appointment_id']);
				db_exec(<<<EOQ
					INSERT INTO pairs ( week_id, rooster_version, log_id,
						paired_log_id, appointment_id )
					VALUES ( ?, ?, ?, ?, ? )
					EOQ, $week_id, $rooster_version, $a['id'], $b['id'],
					$b['appointment_id']);
                        } else if ($pairs[$b['id']]['appointment1_id'] == $a['id']) {
                                // ok, already available
                        } else {
				// soft-overwrite old pair
				db_exec(<<<EOQ
					INSERT INTO pairs ( prev_pair_id, week_id, rooster_version,
						log_id, paired_log_id, appointment_id )
					VALUES ( ?, ?, ?, ?, ?, ? )
					EOQ, $pairs[$b['id']]['pair_id'], $week_id,
					$rooster_version, $b['id'], $a['id'], $a['appointment_id']);
				db_exec(<<<EOQ
					INSERT INTO pairs ( prev_pair_id, week_id, rooster_version,
						log_id, paired_log_id, appointment_id )
					VALUES ( ?, ?, ?, ?, ?, ? )
					EOQ, $pairs[$a['id']]['pair_id'], $week_id,
					$rooster_version, $a['id'], $b['id'], $b['appointment_id']);
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
			SELECT UNIX_TIMESTAMP(locking_last_timestamp) timestamp
			FROM locking
			WHERE locking_id = ?
			EOQ, $type);
		if ($lock === NULL) {
			$affected_rows = db_exec(<<<EOQ
				INSERT IGNORE INTO locking ( locking_id, locking_pid, locking_status )
				VALUES ( ?, ?, ? )
				EOQ, $type, $PID, $string);

			if ($affected_rows) return 1; // success

			// we've lost the race, retry
		} else {
			// there already is a lock... let's look how old it is
			$age = time() - $lock['timestamp'];
			if ($age < 120) return 0; // lock is not old enough
			
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
	$affected_rows = db_exec(<<<EOQ
		UPDATE locking
		SET locking_status = ?, locking_last_timestamp = NOW(6)
		WHERE locking_id = ? AND locking_pid = ?
		EOQ, $string, $type, $PID);
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
	else if ($bw == 'w') return $les['f_s'] == 'invalid';
}

function show_replacement($les, $bw) {
	if ($bw == 'b') return false;
	else if ($bw == 'w') return $les['f_s'] == 'normal' &&
		$les['s_id'] && $les['f_aid'] != $les['s_aid'];
}

function show_normal($les, $bw) {
	if ($bw == 'w') return true;
	else if ($bw == 'b') return ($les['f_s'] != 'new') &&
		( ( $les['f_s'] == 'cancelled' || $les['f_s'] == 'invalid' ) ||
			( $les['f_s'] == 'normal'  && !$les['s_id'] ) );
}

function master_query($entity_ids, $kind, $rooster_version, $participations_version, $week_id) {
	if (!$entity_ids) {
		$join = '';
		$where = '';
	} else if ($kind == 'students') {
		$join = <<<EOJ
			JOIN (
				SELECT week_id, appointment_instance_zid, students_egrp_id
				FROM participations 
				LEFT JOIN (
					SELECT week_id, prev_participation_id AS participation_id,
						participation_id AS obsolete
					FROM participations
					WHERE participations_version <= $participations_version
				) AS next_participations USING (week_id, participation_id)
				WHERE participations_version <= $participations_version
				AND obsolete IS NULL
			) AS valid_participations USING (week_id, appointment_instance_zid)
			JOIN entities2egrps
			ON entities2egrps.egrp_id = valid_participations.students_egrp_id\n
			EOJ;
		$where = " AND entity_id IN ( $entity_ids )";
	} else if ($kind == 'groups' || $kind == 'subjects' ||
			$kind == 'teachers' || $kind == 'locations') {
		$join = "JOIN entities2egrps ON entities2egrps.egrp_id = f_a.{$kind}_egrp_id\n";
		$where = " AND entity_id IN ( $entity_ids )";
	} else fatal("impossible value of \$kind");

	return db_query(<<<EOQ
		SELECT f_l.log_id f_id, f_a.appointment_id f_aid, f_l.appointment_instance_zid zid,
			f_a.appointment_day f_d, f_a.appointment_timeSlot f_u,
			f_l.appointment_state f_s, f_a.groups f_groups, f_a.subjects f_subjects,
			f_a.teachers f_teachers, f_a.locations f_locations,
			pairs.paired_log_id s_id, s_a.appointment_id s_aid, s_a.appointment_day s_d,
			s_a.appointment_timeSlot s_u, s_a.groups s_groups, s_a.subjects s_subjects,
			s_a.teachers s_teachers, s_a.locations s_locations
		FROM log AS f_l
		LEFT JOIN (
			SELECT week_id, prev_log_id AS log_id, log_id AS obsolete
			FROM log
			WHERE rooster_version <= $rooster_version
		) AS next_log USING (log_id, week_id)
		JOIN appointments AS f_a USING (appointment_id)
		{$join}LEFT JOIN (
			SELECT week_id, log_id, paired_log_id, appointment_id FROM pairs
			LEFT JOIN (
				SELECT week_id, prev_pair_id AS pair_id, pair_id obsolete
				FROM pairs
				WHERE rooster_version <= $rooster_version
			) AS next_pairs USING (pair_id, week_id)
			WHERE rooster_version <= $rooster_version AND obsolete IS NULL
		) AS pairs USING (log_id, week_id)
		LEFT JOIN appointments AS s_a ON s_a.appointment_id = pairs.appointment_id
		WHERE obsolete IS NULL AND f_l.week_id = $week_id
		AND f_l.rooster_version <= $rooster_version$where
		AND f_a.appointment_timeSlot > 0
		ORDER BY f_u, f_d,
		CASE f_s WHEN 'cancelled' THEN 0
			WHEN 'invalid' THEN 1 WHEN 'normal' THEN 2 WHEN 'new' THEN 3 END
		EOQ);
}

function lln_query($entity_ids, $rooster_version, $participations_version, $week_id) {
	return db_single_field(<<<EOQ
		SELECT GROUP_CONCAT(DISTINCT log2students.entity_id)
		FROM log
		LEFT JOIN (
			SELECT week_id, prev_log_id AS log_id, log_id AS obsolete
			FROM log
			WHERE rooster_version <= ?
		) AS next_log USING (log_id, week_id)
		JOIN appointments USING (appointment_id)
		JOIN entities2egrps AS appointments2groups
		ON appointments2groups.egrp_id = appointments.groups_egrp_id
		JOIN (
			SELECT week_id, appointment_instance_zid, students_egrp_id
			FROM participations 
			LEFT JOIN (
				SELECT week_id, prev_participation_id AS participation_id,
					participation_id AS obsolete
				FROM participations
				WHERE participations_version <= ?
			) AS next_participations USING (week_id, participation_id)
			WHERE participations_version <= ? AND obsolete IS NULL
		) AS valid_participations USING (week_id, appointment_instance_zid)
		JOIN entities2egrps AS log2students
		ON log2students.egrp_id = valid_participations.students_egrp_id
		WHERE appointments2groups.entity_id IN ( $entity_ids )
		AND rooster_version <= ? AND week_id = ?
		EOQ, $rooster_version, $rooster_version, $participations_version, $participations_version, $week_id);
}

// warn when there are doubly named things
function check_doubles() {
	$doubles = db_all_assoc_rekey(<<<EOQ
		SELECT entity_name, COUNT(entity_id) clash
		FROM entities
		GROUP BY entity_name
		HAVING clash > 1
		EOQ);

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

function get_weeks_to_update($past = false) {
	if (!$past) {
		// now + maandag - vrijdag eind van het 9e uur (16:50 op het ovc)
		$offset = $_SERVER['REQUEST_TIME'] + 2*24*60*60 + (10 + 7*60)*60;
		$current_week = date('W', $offset);
		$current_year = date('o', $offset);
		$future= " AND year > $current_year OR ( year = $current_year AND week >= $current_week )";
	} else $future = '';
	if (config('RESPECT_HOLIDAYS') == 'true') {
		$respect_holidays = ' AND ( ma OR di OR wo OR do OR vr )';
	} else $respect_holidays = '';

	return db_all_assoc(<<<EOQ
		SELECT week_id, year, week, sisy_project, sisy_zid,
			UNIX_TIMESTAMP(monday_timestamp) monday_unix_timestamp
		FROM weeks
		JOIN sisys USING (sisy_id)
		WHERE sisy_archived != 1 AND employeeCanViewProjectSchedules = 1$future$respect_holidays
		ORDER BY year, week, sisy_id
		EOQ);
}

// this function will fail if the 'appointments' lock is not held
function update_appointments_in_week($week) {
	lock_renew('appointments', 'updating appointments in '.$week['sisy_project'].' '.
		$week['week'].' (week_id='.$week['week_id'].')');

	// we need these fields from the API
	$fields = array(
		'id', 'appointmentInstance', 'startTimeSlot', 'endTimeSlot', 'start',
		'end', 'branchOfSchool', 'type', 'subjects', 'teachers', 'groupsInDepartments',
		'locationsOfBranch', 'cancelled', 'valid', 'new', 'hidden', 'created', 'students',
		'lastModified', 'appointmentLastModified', 'schedulerRemark');

	$start = $week['monday_unix_timestamp'];
	$end = $start + 7*24*60*60;

	$week_last_sync = time(); // record the time that the sync started

	$roosters = db_all_assoc_rekey(<<<EOQ
		SELECT rooster_id, UNIX_TIMESTAMP(rooster_lastModified) lastModified, rooster_ok ok
		FROM roosters
		WHERE week_id = ?
		EOQ, $week['week_id']);

	$pversions = db_all_assoc_rekey(<<<EOQ
		SELECT pversion_id, UNIX_TIMESTAMP(pversion_lastModified) lastModified, pversion_ok ok
		FROM pversions
		WHERE week_id = ?
		EOQ, $week['week_id']);

	$rooster_id = 0;
	$rooster_version = 0;
	$last_ok_rooster_version = 0;
	$rooster_versions = array();
	$rooster_modifiedSince = NULL;
	foreach ($roosters as $id => $rooster) {
		$rooster_version++;
		if ($last_ok_rooster_version + 1 == $rooster_version && $rooster['ok']) {
			$last_ok_rooster_version++;
			$rooster_modifiedSince = $rooster['lastModified'];
			$rooster_id = $id;
		}
		$rooster_versions[$rooster_version] = $id;
	}

	if ($last_ok_rooster_version != $rooster_version) {
		fatal("rooster broken..., last_ok_rooster_version ($last_ok_rooster_version) != ".
			"rooster_version ($rooster_version)");
		// all roosters after the bad one must be removed in reverse (log_id DESC)
		// order, and an update must be started from the last good version
		// this can happen if the update is interrupted (but only the last version
		// will be not ok)
	}

	$highest_version_in_log = db_single_field(
		'SELECT MAX(rooster_version) FROM log WHERE week_id = ?', $week['week_id']);

	if ($highest_version_in_log > $rooster_version) {
		fatal("found log records with version $highest_version_in_log, ".
			"while the current version of the schedule is $rooster_version");
	} else if ($highest_version_in_log < $rooster_version) {
		fatal("found no records with current version $rooster_version, ".
			"highest version number found is $highest_version_in_log");
	} 

	$pversion_id = 0;
	$participations_version = 0;
	$last_ok_participations_version = 0;
	$participations_versions= array();
	$participations_modifiedSince = NULL;
	foreach ($pversions as $id => $pversion) {
		$participations_version++;
		if ($last_ok_participations_version +1 == $participations_version && $pversion['ok']) {
			$last_ok_participations_version++;
			$participations_modifiedSince = $pversion['lastModified'];
			$pversion_id = $id;
		}
		$participations_versions[$participations_version] = $id;
	}
	
	if ($last_ok_participations_version != $participations_version) {
		fatal("participations broken..., last_ok_participations_version ".
			"($last_ok_participations_version) != ".
			"participations_version ($participations_version)");
	}

	$highest_version_in_participations = db_single_field(
		'SELECT MAX(participations_version) FROM participations WHERE week_id = ?',
		$week['week_id']);

	if ($highest_version_in_participations > $participations_version) {
		fatal("found log records with version $highest_version_in_participations, ".
			"while the current version of the schedule is $participations_version");
	} else if ($highest_version_in_participations < $participations_version) {
		fatal("found no records with current version $participations_version, ".
			"highest version number found is $highest_version_in_participations");
	} 

	if ($participations_modifiedSince < $rooster_modifiedSince)
		fatal("impossible: participations_modifiedSince ($participations_modifiedSince)".
			" < rooster_modifiedSince ($rooster_modifiedSince)");

	// so, there is only one modifiedsince
	$modifiedSince = $participations_modifiedSince;

	// request data, first try the cached data
	$args = array( 'includeHidden', 'true', 'start', $start, 'end', $end,
		'fields', $fields, 'schoolInSchoolYear', $week['sisy_zid'] );
	if ($modifiedSince) {
		$args[] = 'modifiedSince';
		$args[] = $modifiedSince;
	}
	$json = zportal_vGET_data_cached('appointments', $args);
	// if we did not get data from the cache, do a real request
	// no data means:
	// - we cached a request that returned no data
	// - there is no request in the cache
	if (!count($json)) $json = zportal_vGET_data('appointments', $args);

	// merge appointments in week starts with the current $rooster_version
	// rooster_version and rooster_id are passed by reference!
	merge_appointments_in_week($json, $rooster_version, $week['week_id'], $rooster_id, $participations_version, $pversion_id);

	$rooster_ok = $rooster_id?db_single_field(
		'SELECT rooster_ok FROM roosters WHERE rooster_id = ?', $rooster_id):1;
	$pversion_ok = $pversion_id?db_single_field(
		'SELECT pversion_ok FROM pversions WHERE pversion_id = ?', $pversion_id):1;

	if (!$rooster_ok) {
		// we need to make pairs, because there is a new schedule version
		generate_pairs($week['week_id'], $rooster_version);

		// check what type of schedule we have
		$type = get_rooster_type($week['week_id'], $rooster_version);

		// and store it
		db_exec('UPDATE roosters SET rooster_type = ? WHERE rooster_id = ?',
			$type, $rooster_id);
	}

	if ($pversion_ok && $rooster_ok) {
		// no updates to schedule and participations
		db_exec('UPDATE weeks SET week_last_sync = FROM_UNIXTIME( ? ) WHERE week_id = ?',
			$week_last_sync, $week['week_id']);
	} else if ($pversion_ok && !$rooster_ok) {
		// only update to schedule
		db_exec(<<<EOQ
			UPDATE roosters JOIN weeks USING (week_id)
			SET rooster_ok = 1, week_last_sync = FROM_UNIXTIME( ? )
			WHERE rooster_id = ?
			EOQ, $week_last_sync, $rooster_id);
	} else if (!$pversion_ok && $rooster_ok) {
		// only update to participations
		db_exec(<<<EOQ
			UPDATE pversions JOIN weeks USING (week_id)
			SET pversion_ok = 1, week_last_sync = FROM_UNIXTIME( ? )
			WHERE pversion_id = ?
			EOQ, $week_last_sync, $pversion_id);
	} else { // both have been updated
		db_exec(<<<EOQ
			UPDATE weeks
			JOIN pversions USING (week_id)
			JOIN roosters USING (week_id)
			SET pversion_ok = 1, rooster_ok = 1, week_last_sync = FROM_UNIXTIME( ? )
			WHERE pversion_id = ? AND rooster_id = ?
			EOQ, $week_last_sync, $pversion_id, $rooster_id);
	}
}

function merge_appointments_in_week($json, &$rooster_version, $week_id,
		&$rooster_id, &$pversions_version, &$pversion_id) {
	lock_renew('appointments', 'merge '.count($json).' appointments as version '.
		$rooster_version.' in week_id='.$week_id);

	foreach ($json as $a) {
		merge_appointment_in_week($a, $rooster_version, $week_id,
			$rooster_id, $pversions_version, $pversion_id);
	}
}

function get_appointment_id($a) {
	$groupsInDepartments = dereference($a, 'groupsInDepartments');
	$locationsOfBranch = dereference($a, 'locationsOfBranch');

	$groups = count($groupsInDepartments)?implode(',',
		functionalsort(array_map('search_group_on_zid', $groupsInDepartments))):'';

	$subjects = implode(',',array_map('capitalize_subject',
		functionalsort(dereference($a, 'subjects'))));

	$locations = count($locationsOfBranch)?implode(',',
		functionalsort(array_map('search_location_on_zid', $locationsOfBranch))):'';

	$teachers = implode(',',array_map('capitalize_teacher',
		functionalsort(dereference($a, 'teachers'))));

	$bos_id = db_get_id('bos_id', 'boss', 'bos_zid',
		dereference($a, 'branchOfSchool'));

	$groups_egrp_id = db_get_egrp_id($groups, 'search_on_name');
	$subjects_egrp_id = db_get_egrp_id($subjects, 'search_subject');
	$teachers_egrp_id = db_get_egrp_id($teachers, 'search_teacher');
	$locations_egrp_id = db_get_egrp_id($locations, 'search_on_name');

	$type_text_id = db_get_text_id(dereference($a, 'type'));
	$schedulerRemark_text_id = db_get_text_id(dereference($a, 'schedulerRemark'));

	$timeSlot = dereference($a, 'startTimeSlot');
	if (!$timeSlot || $timeSlot != dereference($a, 'endTimeSlot')) $timeSlot = 0;

	$start = dereference($a, 'start');
	$duration = dereference($a, 'end') - $start;

	return db_get_id_new('appointment_id', 'appointments',
		"bos_id = $bos_id", NULL,
		"type_text_id = $type_text_id", NULL,
		"groups_egrp_id = $groups_egrp_id", NULL,
		"subjects_egrp_id = $subjects_egrp_id", NULL,
		"teachers_egrp_id = $teachers_egrp_id", NULL,
		"locations_egrp_id = $locations_egrp_id", NULL,
		'groups = ?', $groups,
		'subjects = ?', $subjects,
		'teachers = ?', $teachers,
		'locations = ?', $locations,
		'appointment_day = WEEKDAY(FROM_UNIXTIME(?)) + 1', $start,
		'appointment_time = FROM_UNIXTIME(?)', $start,
		'appointment_timeSlot = ?', $timeSlot,
		'appointment_duration = ?', $duration,
		"schedulerRemark_text_id = $schedulerRemark_text_id", NULL);
}

function merge_appointment_in_week($a, &$rooster_version, $week_id, &$rooster_id, &$participations_version, &$pversion_id) {
	lock_renew('appointments', 'merge appointment as version '.$rooster_version.
		' in week_id='.$week_id);
	echo("id=".dereference($a, 'id').' instance='.dereference($a, 'appointmentInstance').
		' lastModified='.dereference($a, 'lastModified').' created='.
		dereference($a, 'created').' hidden='.dereference($a, 'hidden')."\n");

	$zid = dereference($a, 'id');
	$instance_zid = dereference($a, 'appointmentInstance');

	// let's see if we already have this appointment
	$old_appointment = db_single_row(<<<EOQ
		SELECT log_id, rooster_version FROM log
		LEFT JOIN (
			SELECT week_id, prev_log_id AS log_id, log_id AS obsolete
			FROM log
			WHERE rooster_version <= ?
		) next_log USING (log_id, week_id)
		WHERE week_id = ? AND rooster_version <= ? AND appointment_zid = ? AND obsolete IS NULL
		EOQ, $week_id, $rooster_version, $rooster_version, $zid);

	if ($old_appointment) fatal("we already have an old version of this version of the ".
		"appointment, merging is yet to be implemented");

	/* first handle the appointment */
	$appointment_lastModified = dereference($a, 'appointmentLastModified');	
	$hidden = dereference($a, 'hidden');
	if (!$hidden) {
		$appointment_id = get_appointment_id($a);
		$created = dereference($a, 'created');
		$valid = dereference($a, 'valid');
                $new = dereference($a, 'new');
		$cancelled = dereference($a, 'cancelled');
		if ($valid && $new && !$cancelled) $state = 'new';
		else if ($valid && !$new && $cancelled) $state = 'cancelled';
		else if ($valid && !$new && !$cancelled) $state = 'normal';
		else if (!$valid && !$new && !$cancelled) $state = 'invalid';
		else fatal("impossible combination of valid, new and cancelled for non ".
			"hidden appointment");

		/* since we can't handle updating appointments yet and this one is 
		 * not hidden, we must check if we already have a new rooster open */
		$ok = db_single_field("SELECT rooster_ok FROM roosters WHERE rooster_id = ?",
			$rooster_id);
		if ($rooster_version == 0 || $ok) {
			db_exec('INSERT INTO roosters ( week_id ) VALUES ( ? )', $week_id);
			$rooster_id = db_last_insert_id();
			$rooster_version++;
			echo("opened new rooster version $rooster_version at rooster_id = $rooster_id\n");
		}

		db_exec(<<<EOQ
			INSERT INTO log ( prev_log_id, week_id, rooster_version, appointment_id,
				appointment_zid, appointment_instance_zid, appointment_state,
				appointment_created, appointment_lastModified )
			VALUES ( ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?) )
			EOQ, NULL, $week_id, $rooster_version, $appointment_id, $zid,
			$instance_zid, $state, $created, $appointment_lastModified);
	} else if ($old_appointment) {
		fatal("we can't handle existing appointments that must be hidden yet");
	} 

	db_exec(<<<EOQ
		UPDATE roosters
		SET rooster_lastModified = FROM_UNIXTIME(?)
		WHERE rooster_id = ? AND rooster_lastModified < FROM_UNIXTIME(?)
		EOQ, $appointment_lastModified, $rooster_id, $appointment_lastModified);

	// we don't care about hidden appointments after this
	if ($hidden) return;

	/* now handle the participation */
	$participation_lastModified = dereference($a, 'lastModified');

	$students = implode(',', functionalsort(dereference($a, 'students')));
	$students_egrp_id = db_get_egrp_id($students, 'search_on_name');

	// do we already have participation data?
	$old_participation = db_single_row(<<<EOQ
		SELECT participation_id, participations_version, students_egrp_id
		FROM participations
		LEFT JOIN (
			SELECT appointment_instance_zid, prev_participation_id AS participation_id,
				participation_id AS obsolete
			FROM participations
			WHERE participations_version <= ?
		) AS next_participations USING (appointment_instance_zid, participation_id)
		WHERE appointment_instance_zid = ? AND participations_version <= ? AND obsolete IS NULL
		EOQ, $participations_version, $instance_zid, $participations_version);
	
	if ($old_participation) {
		if ($old_participation['participations_version'] == $participations_version) {
			if ($students_egrp_id != $old_participation['students_egrp_id'])
				fatal("weird?!?! participation in same instance_zid is different ".
					"in same run");
		} else {
			print_r($old_participation);
			echo("students_egrp_id=$students_egrp_id\n");
			fatal("we already have old participation information, can't deal with it yet");
		}
	} else {
		/* since we can't handle updating participations yet
		 * we must check if we already have a new pversions open */
		$ok = db_single_field("SELECT pversion_ok FROM pversions WHERE pversion_id = ?",
			$pversion_id);
		if ($participations_version == 0 || $ok) {
			db_exec('INSERT INTO pversions ( week_id ) VALUES ( ? )', $week_id);
			$pversion_id = db_last_insert_id();
			$participations_version++;
			echo("opened new participations version $rooster_version at rooster_id = $rooster_id\n");
		}
		db_exec(<<<EOQ
			INSERT INTO participations ( prev_participation_id, week_id,
				participation_lastModified, participations_version,
				appointment_instance_zid, students_egrp_id )
			VALUES ( ?, ?, FROM_UNIXTIME(?), ?, ?, ? )
			EOQ, NULL, $week_id, $participation_lastModified, $participations_version,
			$instance_zid, $students_egrp_id);
	}


	db_exec(<<<EOQ
		UPDATE pversions
		SET pversion_lastModified = FROM_UNIXTIME(?)
		WHERE pversion_id = ? AND pversion_lastModified < FROM_UNIXTIME(?)
		EOQ, $participation_lastModified, $pversion_id, $participation_lastModified);
}

function get_rooster_type($week_id, $rooster_version) {
	//echo("week_id=$week_id, rooster_version=$rooster_version\n");
	$bool = db_single_field(<<<EOQ
		SELECT BIT_OR(appointment_state != 'normal')
		FROM log
		LEFT JOIN (
			SELECT prev_log_id AS log_id, log_id AS obsolete
			FROM log
			WHERE week_id = ? AND rooster_version <= ?
		) next_log USING (log_id)
		WHERE week_id = ? AND rooster_version <= ? AND obsolete IS NULL
		EOQ, $week_id, $rooster_version, $week_id, $rooster_version);
	if ($bool) return 'week';
	else return 'basis';
}

function html_print_r($array) {
	?><pre><?php print_r($array); ?></pre><?php
}
?>
