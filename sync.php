#!/usr/bin/php
<?
require_once('common.php');
require_once('zportal.php');

// release the lock if the process gets terminated
function shutdown_function() {
	lock_release('appointments');
}

set_employee_token();

$weeks = get_weeks_to_update();

register_shutdown_function('shutdown_function');

while (!lock_acquire('appointments', 'updating appointments in '.count($weeks).' weken')) {
	echo("lock busy, waiting...\n");
	sleep(3);
}

foreach ($weeks as $week)
	update_appointments_in_week($week);

// lock release automatically when the script finished

?>
