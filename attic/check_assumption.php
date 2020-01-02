#!/usr/bin/php
<?php
$_SERVER['EZ_PORTAL_INSTITUTION'] = 'ovc';

require_once('common.php');

$sisy_id = 4;
for ($week = 1; $week <= 52; $week++) {

$rooster = db_single_row(<<<EOQ
SELECT week_id, week, COUNT(rooster_id) version, BIT_OR(rooster_ok) ok
FROM weeks
JOIN roosters USING (week_id)
WHERE sisy_id = ?
GROUP BY week_id
HAVING week = ?
EOQ
	, $sisy_id, $week);

if (!$rooster) continue;

print_r($rooster);
$rooster_version = dereference($rooster, 'version');
$week_id = dereference($rooster, 'week_id');

$appointments = db_all_assoc(<<<EOQ
SELECT appointment_instance_zid, COUNT(log_id) AS count FROM log
LEFT JOIN (
	SELECT week_id, prev_log_id AS log_id, log_id AS obsolete
	FROM log
	WHERE rooster_version <= ?
) AS log_next USING (log_id, week_id)
WHERE rooster_version <= ? AND week_id = ? AND appointment_instance_zid IS NOT NULL
GROUP BY appointment_instance_zid
HAVING count > 2
EOQ
	, $rooster_version, $rooster_version, $week_id);

print_r($appointments);
}
?>
