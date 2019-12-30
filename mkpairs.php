#!/usr/bin/php
<?
$_SERVER['EZ_PORTAL_INSTITUTION'] = 'ovc';

require_once('common.php');

$roosters = db_all_assoc(<<<EOQ
SELECT COUNT(rooster_id) version, week_id
FROM roosters
WHERE rooster_ok = 1
GROUP BY week_id
EOQ
);
print_r($roosters);

foreach ($roosters as $rooster) {
	for ($i = 1; $i <= $rooster['version']; $i++) 
		generate_pairs($rooster['week_id'], $i);
}

?>
