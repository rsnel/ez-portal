#!/usr/bin/php
<?

require_once('common.php');
require_once('zportal.php');

// set the token of someone that canViewProjectSchedules and canViewProjectNames
set_employee_token();

//update_portal_version();
echo("using portal version ".
	db_single_field("SELECT config_value FROM config WHERE config_key = 'PORTAL'")."\n");

echo("updating schoolsInSchoolYear\n");
update_sisys();
echo("updating branchesOfSchools\n");
update_boss();
echo("updating holidays\n");
update_holidays();
echo("updating users\n");
update_users();
echo("updating categories\n");
update_categories();
echo("updating groups\n");
update_groups();
//recapitalize_lesgroepen();
echo("updating rooms\n");
update_rooms();
echo("updating weeks\n");
update_weeks();
check_doubles();
?>
