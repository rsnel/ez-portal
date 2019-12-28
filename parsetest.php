#!/usr/bin/php
<?
$_SERVER['EZ_PORTAL_INSTITUTION'] = 'ovc';

require_once('common.php');
require_once('zportal.php');

set_employee_token();

// dit is de week die we gaan importeren
$week_info = db_single_row("SELECT week_id, monday_unix_timestamp FROM weeks JOIN sisys USING (sisy_id) WHERE week = 19 AND sisy_zid = 351");

if (!$week_info) fatal("unknown week");
$week_id = $week_info['week_id'];
$start = $week_info['monday_unix_timestamp'];
$end = $start + 7*24*60*60;

$rooster_id = db_single_field('SELECT rooster_id FROM roosters WHERE week_id = ?', $week_id);
if ($rooster_id) fatal("already rooster in selected week");
db_exec('INSERT INTO roosters ( week_id ) VALUES ( ? )', $week_id);
$rooster_id = db_last_insert_id();
echo("rooster_id=$rooster_id\n");

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

$json = zportal_GET_data_cached('appointments', 'includeHidden', 'true',
	'start', $start, 'end', $end, 'fields', $fields, 'schoolInSchoolYear', 351);

$lastModified = 0;

foreach ($json as $appointment) {
	echo("id=".dereference($appointment, 'id').' appointmentInstance='.dereference($appointment, 'appointmentInstance').' lastModified='.dereference($appointment, 'lastModified').' appointmentLastModified='.dereference($appointment, 'appointmentLastModified').' hidden='.dereference($appointment, 'hidden')."\n");
	$groupsInDepartments = dereference($appointment, 'groupsInDepartments');
	$locationsOfBranch = dereference($appointment, 'locationsOfBranch');

	$groups = count($groupsInDepartments)?implode(',', functionalsort(array_map('search_group_on_zid',
		$groupsInDepartments))):'';

	$subjects = implode(',',array_map('capitalize_subject',
		functionalsort(dereference($appointment, 'subjects'))));

	$locations = count($locationsOfBranch)?implode(',', functionalsort(array_map('search_location_on_zid',
		$locationsOfBranch))):'';

	$teachers = implode(',',array_map('capitalize_teacher',
		functionalsort(dereference($appointment, 'teachers'))));

	$students = implode(',', functionalsort(dereference($appointment, 'students')));
	$tohash = $groups."\n".$subjects."\n".$teachers."\n".$locations."\n".$students."\n";
	$bos_id = db_get_id('bos_id', 'boss', 'bos_zid', dereference($appointment, 'branchOfSchool'));

	$groups_egrp_id = db_get_egrp_id($groups, 'search_on_name');
	$subjects_egrp_id = db_get_egrp_id($subjects, 'search_subject');
	$teachers_egrp_id = db_get_egrp_id($teachers, 'search_teacher');
	$locations_egrp_id = db_get_egrp_id($locations, 'search_on_name');
	$students_egrp_id = db_get_egrp_id($students, 'search_on_name');

	$type_text_id = db_get_text_id(dereference($appointment, 'type'));
	$changeDescription_text_id = db_get_text_id(dereference($appointment, 'changeDescription'));
	$startTimeSlotName_text_id = db_get_text_id(dereference($appointment, 'startTimeSlotName'));
	$endTimeSlotName_text_id = db_get_text_id(dereference($appointment, 'endTimeSlotName'));
	$content_text_id = db_get_text_id(dereference($appointment, 'content'));
	$remark_text_id = db_get_text_id(dereference($appointment, 'remark'));
	$schedulerRemark_text_id = db_get_text_id(dereference($appointment, 'schedulerRemark'));

	$prev_appointment_id = NULL;

	$appointment_id = db_get_id('appointment_id', 'appointments',
		'prev_appointment_id', $prev_appointment_id,
		'rooster_id', $rooster_id,
		'appointment_zid', dereference($appointment, 'id'),
		'appointment_instance_zid', dereference($appointment, 'appointmentInstance'),
		'appointment_start', from_unixtime(dereference($appointment, 'start')),
		'appointment_end', from_unixtime(dereference($appointment, 'end')),
		'bos_id', $bos_id,
		'type_text_id', $type_text_id,
		'groups_egrp_id', $groups_egrp_id,
		'subjects_egrp_id', $subjects_egrp_id,
		'teachers_egrp_id', $teachers_egrp_id,
		'locations_egrp_id', $locations_egrp_id,
		'students_egrp_id', $students_egrp_id,
		'appointment_optional', dereference($appointment, 'optional'),
		'appointment_valid', dereference($appointment, 'valid'),
		'appointment_cancelled', dereference($appointment, 'cancelled'),
		'appointment_modified', dereference($appointment, 'modified'),
		'appointment_teacherChanged', dereference($appointment, 'teacherChanged'),
		'appointment_groupChanged', dereference($appointment, 'groupChanged'),
		'appointment_locationChanged', dereference($appointment, 'locationChanged'),
		'appointment_timeChanged', dereference($appointment, 'timeChanged'),
		'appointment_moved', dereference($appointment, 'moved'),
		'appointment_created', from_unixtime(dereference($appointment, 'created')),
		'appointment_hidden', dereference($appointment, 'hidden'),
		'appointment_new', dereference($appointment, 'new'),
		'appointment_lastModified', from_unixtime(
			dereference($appointment, 'lastModified')),
		'appointment_appointmentLastModified',
			from_unixtime(dereference($appointment, 'appointmentLastModified')),
		'appointment_startTimeSlot', dereference($appointment, 'startTimeSlot'),
		'appointment_endTimeSlot', dereference($appointment, 'endTimeSlot'),
		'changeDescription_text_id', $changeDescription_text_id,
		'startTimeSlotName_text_id', $startTimeSlotName_text_id,
		'endTimeSlotName_text_id', $endTimeSlotName_text_id,
		'content_text_id', $content_text_id,
		'remark_text_id', $remark_text_id,
		'schedulerRemark_text_id', $schedulerRemark_text_id);

	$next_lastModified = dereference($appointment, 'lastModified');
	if ($next_lastModified > $lastModified) $lastModified = $next_lastModified; 
	echo("appointment_id=$appointment_id\n");
}

db_exec('UPDATE roosters SET rooster_last_modified = FROM_UNIXTIME(?), rooster_ok = 1 WHERE rooster_id = ?',
	$lastModified, $rooster_id);

?>
