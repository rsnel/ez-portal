#!/usr/bin/php
<?

require_once('common.php');
require_once('zportal.php');

$sisyinfo = get_sisyinfo();
$count_branches = db_single_field("SELECT COUNT(bos_id) FROM boss WHERE sisy_id = ?", $sisyinfo['sisy_id']);
if ($count_branches > 1) fatal("can't handle multiple branches at the moment");

// set the token of someone that canViewProjectSchedules and canViewProjectNames
set_random_token($sisyinfo);

//echo("updating schoolsInSchoolYear\n");
//update_sisys();
//echo("updating branchesOfSchools\n");
//update_boss();
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
//echo("updating weeks\n");
//update_weeks($sisyinfo);

check_doubles();

/*
//print_r($sisyinfo);
//$infos = zportal_GET_data('locationofbranches');
//print_r($infos);
 */
?>
