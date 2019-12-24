#!/usr/bin/php
<?

require_once('common.php');
require_once('zportal.php');

$sisyinfo = get_sisyinfo();
//set_random_token($sisyinfo);

$sanity = db_all_assoc_rekey(<<<EOQ
SELECT entity_name, COUNT(entity_id) clash FROM entities GROUP BY entity_name HAVING clash > 1
EOQ
);

if (count($sanity) > 0) {
	echo("warming, some names appear double in entities list, see here:\n");
	print_r($sanity);
}

//echo("updating schoolsInSchoolYear\n");
//update_sisys();
//echo("updating holidays\n");
//update_holidays();
//echo("updating users\n");
//update_users();
//echo("updating categories\n");
//update_categories();
//echo("updating groups\n");
//update_groups();
//echo("updating rooms\n");
//update_rooms();
echo("updating weeks\n");
update_weeks($sisyinfo);

/*
//print_r($sisyinfo);
//$infos = zportal_GET_data('locationofbranches');
//print_r($infos);

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

 */
?>
