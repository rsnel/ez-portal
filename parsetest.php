#!/usr/bin/php
<?
$_SERVER['EZ_PORTAL_INSTITUTION'] = 'ovc';
// out-35-ok2
// start=1566770400&end=1567375200&fields=appointmentInstance%2Cid%2CstartTimeSlot%2CendTimeSlot%2Cstart%2Cend%2CbranchOfSchool%2Ctype%2Coptional%2Csubjects%2Cteachers%2CgroupsInDepartments%2ClocationsOfBranch%2CcapacityManually%2Ccapacity%2CexpectedStudentCount%2CavailableSpace%2Ccancelled%2CtimeChanged%2CteacherChanged%2CgroupChanged%2ClocationChanged%2CchangeDescription%2CschedulerRemark%2Ccontent%2Cvalid%2Cnew%2Chidden%2Cmodified%2Cmoved%2Cstudents%2ClastModified%2CappointmentLastModified&schoolInSchoolYear=351

require_once('common.php');
require_once('zportal.php');

$access_token = db_single_field("SELECT access_token FROM access JOIN users USING (entity_id) WHERE isEmployee = 1");

if (!$access_token) fatal("no employee token available");

zportal_set_access_token($access_token);

//$string = file_get_contents("out-35-ok2.json");
//$json = json_decode($string, 'true');
//$json = dereference($json, 'response', 'data');

// dit is de week die we gaan importeren
$week_info = db_single_row("SELECT week_id, monday_unix_timestamp FROM weeks JOIN sisys USING (sisy_id) WHERE week = 36 AND sisy_zid = 351");

if (!$week_info) fatal("unknown week");
$week_id = $week_info['week_id'];
$start = $week_info['monday_unix_timestamp'];
$end = $start + 7*24*60*60;

$rooster_id = db_single_field('SELECT rooster_id FROM roosters WHERE week_id = ?', $week_id);
if ($rooster_id) fatal("already rooster in selected week");
db_exec('INSERT INTO roosters ( week_id ) VALUES ( ? )', $week_id);
$rooster_id = db_last_insert_id();
echo("rooster_id=$rooster_id\n");

$json = zportal_GET_data('appointments','start', $start, 'end', $end, 'fields', 'appointmentInstance,id,startTimeSlot,endTimeSlot,start,end,branchOfSchool,type,optional,subjects,teachers,groupsInDepartments,locationsOfBranch,cancelled,timeChanged,teacherChanged,groupChanged,locationChanged,changeDescription,valid,new,hidden,modified,moved,students,lastModified,appointmentLastModified', 'schoolInSchoolYear', 351);

$lastModified = 0;

foreach ($json as $appointment) {
	print_r($appointment);
	if (dereference($appointment, 'hidden')) fatal('hidden appointments not implemented');
	$groupsInDepartments = dereference($appointment, 'groupsInDepartments');
	$locationsOfBranch = dereference($appointment, 'locationsOfBranch');

	$groups = count($groupsInDepartments)?implode(',', functionalsort(array_map('search_on_zid',
		$groupsInDepartments))):'';

	$subjects = implode(',',array_map('capitalize_subject',
		functionalsort(dereference($appointment, 'subjects'))));

	$locations = count($locationsOfBranch)?implode(',', functionalsort(array_map('search_on_zid',
		$locationsOfBranch))):'';

	$teachers = implode(',',array_map('capitalize_teacher',
		functionalsort(dereference($appointment, 'teachers'))));

	$students = implode(',', functionalsort(dereference($appointment, 'students')));
	$tohash = $groups."\n".$subjects."\n".$teachers."\n".$locations."\n".$students."\n";
	$bos_id = db_get_id('bos_id', 'boss', 'bos_zid', dereference($appointment, 'branchOfSchool'));
	$type = dereference($appointment, 'type');
	if ($type != 'lesson' && $type != 'activity' && $type != 'exam' && $type != 'choice' && $type != 'talk' && $type != 'other') fatal("appointment type $type not supported yet");

	$groups_egrp_id = db_get_egrp_id($groups, 'search_on_name');
	$subjects_egrp_id = db_get_egrp_id($subjects, 'search_subject');
	$teachers_egrp_id = db_get_egrp_id($teachers, 'search_teacher');
	$locations_egrp_id = db_get_egrp_id($locations, 'search_on_name');

	$agstd_id = db_get_id('agstd_id', 'agstds',
		'groups_egrp_id', $groups_egrp_id,
		'subjects_egrp_id', $subjects_egrp_id,
		'teachers_egrp_id', $teachers_egrp_id,
		'locations_egrp_id', $locations_egrp_id);

	$students_egrp_id = db_get_egrp_id($students, 'search_on_name');

	$prev_appointment_id = NULL;

	$appointment_id = db_get_id('appointment_id', 'appointments',
		'prev_appointment_id', $prev_appointment_id,
		'appointment_zid', dereference($appointment, 'id'),
		'rooster_id', $rooster_id,
		'appointment_instance_zid', dereference($appointment, 'appointmentInstance'),
		'appointment_start', from_unixtime(dereference($appointment, 'start')),
		'appointment_end', from_unixtime(dereference($appointment, 'end')),
		'bos_id', $bos_id,
		'appointment_type', $type,
		'agstd_id', $agstd_id,
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
		'appointment_new', dereference($appointment, 'new'),
		'appointment_lastModified', from_unixtime(
			dereference($appointment, 'lastModified')),
		'appointment_appointmentLastModified',
			from_unixtime(dereference($appointment, 'appointmentLastModified')),
		'appointment_startTimeSlot', dereference($appointment, 'startTimeSlot'),
		'appointment_endTimeSlot', dereference($appointment, 'endTimeSlot'),
		'appointment_changeDescription', dereference($appointment, 'changeDescription'));

	$next_lastModified = dereference($appointment, 'lastModified');
	if ($next_lastModified > $lastModified) $lastModified = $next_lastModified; 
	echo("appointment_id=$appointment_id\n");
}

db_exec('UPDATE roosters SET rooster_last_modified = FROM_UNIXTIME(?), rooster_ok = 1',
	$lastModified);

?>
