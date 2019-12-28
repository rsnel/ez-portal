#!/usr/bin/php
<?
$_SERVER['EZ_PORTAL_INSTITUTION'] = 'ovc';

require_once('common.php');
$rooster_ids = 8;
$max_rooster_id = 8;

$list = db_all_assoc(<<<EOQ
SELECT appointment_id id, appointment_instance_zid zid, appointment_valid valid
FROM appointments
LEFT JOIN (
	SELECT prev_appointment_id appointment_id, appointment_id obsolete
	FROM appointments
	WHERE rooster_id IN ( $rooster_ids )
) AS next_appointments USING ( appointment_id )
WHERE appointment_hidden = 0 AND obsolete IS NULL AND rooster_id IN ( $rooster_ids )
ORDER BY appointment_instance_zid, appointment_id
EOQ
);

$pairs = db_all_assoc_rekey(<<<EOQ
SELECT appointment0_id, appointment1_id, pair_id
FROM pairs
LEFT JOIN (
	SELECT prev_pair_id pair_id, pair_id obsolete
	FROM pairs
	WHERE rooster_id IN ( $rooster_ids )
) AS next_pairs USING (pair_id)
WHERE obsolete IS NULL
EOQ
);

if (!$list || count($list) == 0) fatal("no appointments?");

$b = array_shift($list);

foreach ($list as $a) {
	if ($a['zid'] == $b['zid']) {
		echo("match {$a['id']} <-> {$b['id']} ({$a['valid']}{$b['valid']}\n");
		if (!isset($pairs[$b['id']])) {
			unset($pairs[$b['id']]);
			db_exec('INSERT INTO pairs ( rooster_id, appointment0_id, appointment1_id ) VALUES ( ?, ?, ? )', $max_rooster_id, $b['id'], $a['id']);
		} else if ($pairs[$b['id']]['appointment1_id'] == $a['id']) {
			// ok, already available
		} else {
			db_exec('INSERT INTO pairs ( prev_pair_id, rooster_id, appointment0_id, appointment1_id ) VALUES ( ?, ?, ?, ? )', $pairs[$b['id']]['pair_id'], $max_rooster_id, $b['id'], $a['id']);
		}

	}
	$b = $a;
}

print_r($pairs);
?>
