#!/usr/bin/php
<?
require_once('common.php');
require_once('zportal.php');

// release the lock if the process gets terminated
function shutdown_function() {
	lock_release('appointments');
}

set_employee_token();

/* velden die we opvragen, dit zijn er te veel, maar deze zitten
 * op dit moment (bij het testen) in de cache, dus deze kunnen we opvragen
 * zonder het portal de server te belasten */
$fields = array(
	'id',
	'appointmentInstance',
	'startTimeSlot',
	'endTimeSlot',
	'startTimeSlotName',
	'endTimeSlotName',
	'start',
	'end',
	'branchOfSchool',
	'type',
	'optional',
	'subjects',
	'teachers',
	'groupsInDepartments',
	'locationsOfBranch',
	'cancelled',
	'timeChanged',
	'teacherChanged',
	'groupChanged',
	'locationChanged',
	'changeDescription',
	'valid',
	'new',
	'hidden',
	'modified',
	'moved',
	'created',
	'students',
	'lastModified',
	'appointmentLastModified',
	'content',
	'remark',
	'schedulerRemark'
);

$sisy_id = 4;

$sisy = db_single_row('SELECT * FROM sisys WHERE sisy_id = ?', $sisy_id);
if (!$sisy['employeeCanViewProjectSchedules'])
	fatal('geen toegang tot projectSchedule als employee');


$respect_holidays='';
if (config('RESPECT_HOLIDAYS') == 'true') {
	$respect_holidays = ' AND ( ma OR di OR wo OR do OR vr )';
}

$weeks = db_all_assoc_rekey("SELECT *, UNIX_TIMESTAMP(monday_timestamp) start FROM weeks WHERE sisy_id = ?$respect_holidays ORDER BY year, week", $sisy_id);
//print_r($weeks);

register_shutdown_function('shutdown_function');
$count_weeks = 0;

while (!lock_acquire('appointments', 'updating appointments in '.count($weeks).' weken')) {
	echo("lock busy, waiting...\n");
	sleep(3);
}

foreach ($weeks as $week_id => $week) {
	$count_weeks++;
	lock_renew('appointments', 'updating appointments in '.$count_weeks.'/'.count($weeks).' weken');

	//if ($week_id > 161) exit;
	print_r($week);

	$start = $week['start'];
	$end = $start + 7*24*60*60;
	
	$week_last_sync = time(); // record the time that the sync started

	$rooster = db_single_row(<<<EOQ
SELECT COUNT(rooster_id) version, MAX(rooster_last_modified) last_modified,
	BIT_OR(rooster_ok) ok
FROM roosters
GROUP BY week_id
HAVING week_id = ?
EOQ
	, $week_id);


	$rooster_id = 0; // currently active rooster (in which we are updating)

	if (!$rooster) {
		// er is nog geen rooster in deze week
		$rooster_version = 1; // new version 
		$json = zportal_GET_data_cached('appointments', 'includeHidden', 'true',
			'start', $start, 'end', $end, 'fields', $fields, 'schoolInSchoolYear', 351);
	} else if (!$rooster['ok']) {
		//echo("testing testing, er is een mislukte roosterupdate, jump to end\n");
		//$rooster_id = $rooster['max_rooster_id'];
		//goto testing;
		fatal("er is een mislukte rooster update in week_id=$week_id, {$week['year']}{$week['week']}");
	} else {
		print_r($rooster);
		$rooster_version = $rooster['version'] + 1; // new version
		echo("updaten bestaand rooster nog niet geimplementeerd\n");
		goto finished;
	}

	if (db_single_field(<<<EOQ
SELECT COUNT(*) FROM log WHERE rooster_version = ? AND week_id = ?
EOQ
			, $rooster_version, $week_id))
		fatal("impossible! info about this version is already found in table");

	if (!count($json)) goto finished;

	// the ordering of the data from the portal has some internal logic to it,
	// PRESERVE the ordering! it does not seem to be ordered on any visible field
	// this requires further research:
	// - what is the meaning of lastModified if updates are not ordered by it?
	// - is lastUpdate not changed if an appointment becomes invalid?
	//   (and if so: how does the system know that it needs to send it?)

	db_exec('INSERT INTO roosters ( week_id ) VALUES ( ? )', $week_id);
	$rooster_id = db_last_insert_id();
	$ultimate_lastModified = 0;
	echo("working in rooster_id=$rooster_id\n");
	$count_appointments = 0;
	lock_renew('appointments', 'updating '.count($a).'appointments in '.$count_weeks.'/'.count($weeks).' weken');
	foreach ($json as $a) { // view all appointments
		$count_appointments++;
		lock_renew('appointments', 'updating '.$count_appointments.'/'.count($a).'appointments in '.$count_weeks.'/'.count($weeks).' weken');
		echo("id=".dereference($a, 'id').' instance='.dereference($a, 'appointmentInstance').' lastModified='.dereference($a, 'lastModified').' created='.dereference($a, 'created').' hidden='.dereference($a, 'hidden')."\n");

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

		$students = implode(',', functionalsort(dereference($a, 'students')));
		$bos_id = db_get_id('bos_id', 'boss', 'bos_zid',
			dereference($a, 'branchOfSchool'));

		$groups_egrp_id = db_get_egrp_id($groups, 'search_on_name');
		$subjects_egrp_id = db_get_egrp_id($subjects, 'search_subject');
		$teachers_egrp_id = db_get_egrp_id($teachers, 'search_teacher');
		$locations_egrp_id = db_get_egrp_id($locations, 'search_on_name');
		$students_egrp_id = db_get_egrp_id($students, 'search_on_name');

		$type_text_id = db_get_text_id(dereference($a, 'type'));
		$schedulerRemark_text_id = db_get_text_id(dereference($a, 'schedulerRemark'));

		$timeSlot = dereference($a, 'startTimeSlot');
		if (!$timeSlot || $timeSlot != dereference($a, 'endTimeSlot')) $timeSlot = 0;

		$start = dereference($a, 'start');
		$duration = dereference($a, 'end') - $start;

		$appointment_id = db_get_id_new('appointment_id', 'appointments',
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

		echo("appointment_id=$appointment_id\n");

		$zid = dereference($a, 'id');
		$instance_zid = dereference($a, 'appointmentInstance');
		$created = dereference($a, 'created');
		$lastModified = dereference($a, 'lastModified');
		$appointmentLastModified = dereference($a, 'appointmentLastModified');
		$hidden = dereference($a, 'hidden');
		$valid = dereference($a, 'valid');
		$new = dereference($a, 'new');
		$cancelled = dereference($a, 'cancelled');
		if ($new && !$cancelled) $state = 'new';
		else if (!$new && $cancelled) $state = 'cancelled';
		else if (!$new && !$cancelled) $state = 'normal';

		$log = db_single_row(<<<EOQ
SELECT log_id, rooster_version FROM log
JOIN (
	SELECT prev_log_id AS log_id, log_id obsolete
	FROM log
	WHERE week_id = ? AND rooster_version <= ?
) next_log USING (log_id)
WHERE week_id = ? AND rooster_version <= ? AND appointment_zid = ? AND obsolete IS NOT NULL
EOQ
			, $week_id, $rooster_version, $week_id, $rooster_version, $zid);

		if ($log) {
			print_r($log);
			fatal('not implemented');
		}

		if ($hidden) {
			// no previous version found to hide, store the hidden
			// appointment for reference and hide it immediately

			db_exec(<<<EOQ
INSERT INTO log ( prev_log_id, week_id, rooster_version, appointment_id, appointment_zid,
	appointment_instance_zid, appointment_state, appointment_valid, appointment_created,
	appointment_lastModified, appointment_appointmentLastModified, students_egrp_id )
VALUES (             NULL,    ?, NULL,    ?,    ?,    ?,    ?,    ?,    FROM_UNIXTIME(?), NULL, NULL, ? )
EOQ
				, $week_id, $appointment_id, $zid, $instance_zid, $state,
				$valid, $created, $students_egrp_id );
			$prev_log_id = db_last_insert_id();
			db_exec(<<<EOQ
INSERT INTO log ( prev_log_id, week_id, rooster_version, appointment_id, appointment_zid,
	appointment_instance_zid, appointment_state, appointment_valid, appointment_created,
	appointment_lastModified, appointment_appointmentLastModified )
VALUES ( ?,    ?,    ?, NULL, NULL, NULL, NULL, NULL, NULL,    FROM_UNIXTIME(?),    FROM_UNIXTIME(?) )
EOQ
				, $prev_log_id, $week_id,
				$rooster_version, $lastModified, $appointmentLastModified);

		}  else {
			db_exec(<<<EOQ
INSERT INTO log ( prev_log_id, week_id, rooster_version, appointment_id, appointment_zid,
	appointment_instance_zid, appointment_state, appointment_valid, appointment_created,
	appointment_lastModified, appointment_appointmentLastModified, students_egrp_id )
VALUES ( NULL, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?), FROM_UNIXTIME(?), ? )
EOQ
				, $week_id, $rooster_version, $appointment_id, $zid,
				$instance_zid, $state, $valid, $created,
				$lastModified, $appointmentLastModified, $students_egrp_id );
		}
		
		if ($lastModified > $ultimate_lastModified) $ultimate_lastModified = $lastModified;
	}

testing:
	lock_renew('appointments', 'generating pairs in '.$count_weeks.'/'.count($weeks).' weken');
	generate_pairs($week_id, $rooster_version);
	
	$type = get_rooster_type($week_id, $rooster_version);

finished:
	// finished!
	// set sync time and release rooster version(s) atomically
	if ($rooster_id) { // did we actually update something ?
		//fatal("finishing cleanly after update not implemented yet");
		db_exec('UPDATE roosters JOIN weeks USING (week_id) SET rooster_ok = 1, rooster_type = ?, rooster_last_modified = FROM_UNIXTIME( ? ), week_last_sync = FROM_UNIXTIME( ? ) WHERE rooster_id = ?', $type, $ultimate_lastModified, $week_last_sync, $rooster_id);
	} else {
		db_exec('UPDATE weeks SET week_last_sync = FROM_UNIXTIME( ? ) WHERE week_id = ?',
			$week_last_sync, $week_id);
	}

}

// lock release automatically when the script finished

?>
