<?php

require_once('common.php');
require_once('html.php');

$access_info = get_access_info();

if (!$access_info) {
	html_start($_SERVER['EZ_PORTAL_INSTITUTION']); ?>
	Als je toegang hebt tot <?=$_SERVER['EZ_PORTAL_INSTITUTION']?>.zportal.nl, dan kun je daar inloggen een koppelcode van 12 cijfers aanmaken. Deze code kun je dan hieronder invullen om het rooster te kunnen raadplegen.

<p>
<form action="req_access_token.php" method="POST" accept-charset="UTF-8">
instelling: <?=$_SERVER['EZ_PORTAL_INSTITUTION']?>
<p><label>code: <input autocomplete="off" type="text" placeholder="*** *** *** ***" name="code"></label>
<p><input type="submit" value="Koppel en plaats toegangscookie">
</form>	
<p>Als de koppeling lukt, dan wordt er een cookie met de naam <code>access_token</code> geplaatst in je browser. Dit cookie zorgt ervoor dat je steeds, zonder inloggen of koppelen, bij het rooster kunt. In de website heb je altijd de mogelijkheid om het cookie te verwijderen. Je gaat vanzelfsprekend akkoord met het plaatsen van dit cookie.
<?php	html_end();
	exit;
}

// sanitize input
if (!isset($_GET['bw']) || ( $_GET['bw'] != 'w' && $_GET['bw'] != 'b' )) $_GET['bw'] = 'w';

$bw = $_GET['bw'];

// now + maandag - vrijdag eind van het 9e uur (16:50 op het ovc)
$offset = $_SERVER['REQUEST_TIME'] + 2*24*60*60 + (10 + 7*60)*60;
$current_week = date('W', $offset);
$current_year = date('o', $offset);

$weeks = db_all_assoc_rekey(<<<EOQ
SELECT week_id, year, week, sisy_id, sisy_school, sisy_project,
	UNIX_TIMESTAMP(monday_timestamp) monday_unix_timestamp
FROM weeks
JOIN (
	SELECT DISTINCT week_id FROM roosters WHERE rooster_ok = 1
) AS valid USING (week_id)
JOIN sisys USING (sisy_id)
WHERE sisy_archived != 1
ORDER BY year, week
EOQ
);

if (!$weeks) fatal("rooster nog niet ingelezen");

$default_week_id = db_single_field(<<<EOQ
SELECT week_id FROM weeks
JOIN (
	SELECT DISTINCT week_id FROM roosters WHERE rooster_ok = 1
) AS valid USING (week_id)
WHERE year > ? OR ( year = ? AND week >= ? )
ORDER BY year, week
EOQ
, $current_year, $current_year, $current_week);

// if there is no week at this time, then take the newest week that does exist
if (!$default_week_id) $default_week_id = array_key_last($weeks);
$default_week_info = $weeks[$default_week_id];
$default_week = $default_week_info['week'];

if (!isset($_GET['wk'])) $_GET['wk'] = $default_week;

$week_id = 0;
foreach ($weeks as $idx => $week) {
	if ($_GET['wk'] == $week['week']) {
		$week_id = $idx;
		$safe_week = $week['week'];
		break;
	}
}

if (!$week_id) {
	$safe_week = $default_week;
	$week_id = $default_week_id;
}

//echo("week_id=$week_id\n");
$rooster_info = db_single_row(<<<EOQ
SELECT version,
	DATE_FORMAT(last_modified, CONCAT('wk%v',
		CASE WEEKDAY(last_modified) WHEN 0 THEN 'ma' WHEN 1 THEN 'di' WHEN 2 THEN 'wo'
		WHEN 3 THEN 'do' WHEN 4 THEN 'vr' WHEN 5 THEN 'za' WHEN 6 THEN 'zo' END,
	'%H:%i')) last_modified,
	DATE_FORMAT(last_synced, CONCAT('wk%v',
		CASE WEEKDAY(last_synced) WHEN 0 THEN 'ma' WHEN 1 THEN 'di' WHEN 2 THEN 'wo'
		WHEN 3 THEN 'do' WHEN 4 THEN 'vr' WHEN 5 THEN 'za' WHEN 6 THEN 'zo' END,
	'%H:%i')) last_synced
FROM (
	SELECT COUNT(rooster_id) version,
		MAX(rooster_last_modified) last_modified,
		MAX(week_last_sync) last_synced
	FROM roosters
	JOIN weeks USING (week_id)
	WHERE rooster_ok = 1
	AND week_id = ?
) AS tmp
EOQ
, $week_id);

if (!$rooster_info) fatal("no rooster info?!?!?!");
$rooster_version = $rooster_info['version'];

$thismonday = $weeks[$week_id]['monday_unix_timestamp'];
$sisy_id = $weeks[$week_id]['sisy_id'];
$school = $weeks[$week_id]['sisy_school'];
$project = $weeks[$week_id]['sisy_project'];
$no_bos = db_single_field("SELECT COUNT(bos_id) FROM boss WHERE sisy_id = ?", $sisy_id);
if ($no_bos != 1) fatal("unsupported value of no brach_of_schools $no_bos, only 1 supported at the moment");
$bos_id = db_single_field("SELECT bos_id FROM boss WHERE sisy_id = ?", $sisy_id);

$week_options = '';
$prev_week = NULL;
$next_week = NULL;
$last_week = NULL;
foreach ($weeks as $week) {
	$week_options .= '<option';
	if ($last_week == $safe_week) $next_week = $week['week'];
	if ($week['week'] == $safe_week) {
		$prev_week = $last_week;
		$week_options .= ' selected';
	}
	$last_week = $week['week'];
	$week_options .= ' value="'.($week['week'] == $default_week?'':$week['week']).'">'.$week['week'].'</option>'."\n";
}

$link_tail_wowk = '&amp;bw='.$bw.'&amp;wk=';
$link_tail_tail = '';

if ($safe_week != $default_week) $link_tail = $link_tail_wowk.$safe_week;
else $link_tail = $link_tail_wowk;

$link_tail .= $link_tail_tail.'">';

// show rooster of the owner of the token by default 
if (!isset($_GET['q'])) $_GET['q'] = $access_info['entity_name'];
else $_GET['q'] = trim($_GET['q']);

$qs = explode(',', $_GET['q']);

$result = db_single_row("SELECT * FROM entities WHERE entity_name = ?", $qs[0]);
if ($qs[0] == '*') {
	$entity_type = '*';
	$entity_name = '*';
	$entity_id = NULL;
} else if (!$result) {
	$safe_id = '';
	$entity_type = '';
	$entity_name = '';
	$type = '';
	$res_klas = db_all_assoc_rekey(<<<EOQ
SELECT entity_id, entity_name
FROM entities
JOIN entity_zids USING (entity_id)
WHERE entity_type = 'STAMKLAS'
AND entity_visible
AND sisy_id = ?
AND bos_id = ?
ORDER BY entity_name
EOQ
	, $sisy_id, $bos_id);
	$docfilter = config('DOCFILTER');
	$res_doc = db_all_assoc_rekey(<<<EOQ
SELECT entity_id, entity_name
FROM entities
JOIN users USING (entity_id)
JOIN entity_zids USING (entity_id)
WHERE entity_type = 'PERSOON'
AND isEmployee
AND entity_visible
AND sisy_id = ?
AND $docfilter
ORDER BY entity_name
EOQ
	, $sisy_id);
	$res_lok = db_all_assoc_rekey(<<<EOQ
SELECT entity_id, entity_name
FROM entities
JOIN entity_zids USING (entity_id)
WHERE entity_type = 'LOKAAL'
AND entity_visible
AND sisy_id = ?
AND bos_id = ?
ORDER BY entity_name
EOQ
	, $sisy_id, $bos_id);
	$res_cat = db_all_assoc_rekey(<<<EOQ
SELECT entity_id, entity_name
FROM entities
JOIN entity_zids USING (entity_id)
WHERE entity_type = 'CATEGORIE'
AND entity_visible
AND sisy_id = ?
AND bos_id = ?
ORDER BY entity_name
EOQ
	, $sisy_id, $bos_id);
	$res_vak = db_all_assoc_rekey(<<<EOQ
SELECT entity_id, entity_name
FROM entities 
JOIN entity_zids USING (entity_id)
WHERE entity_type = 'VAK'
AND entity_visible
AND sisy_id = ?
AND bos_id = ?
ORDER BY entity_name
EOQ
	, $sisy_id, $bos_id);

	goto cont;
} else {
	$entity_type = $result['entity_type'];
	$entity_name = $result['entity_name'];
	$entity_id = $result['entity_id'];
}

$entity_multiple = 0;

while (array_shift($qs) && count($qs)) {
	// we hebben extra dingen met komma's
	$result = db_single_row('SELECT entity_type, entity_id, entity_name FROM entities WHERE entity_name = ?', trim($qs[0]));
	if ($result && ( $entity_type == $result['entity_type'] ||
		( $entity_type == 'STAMKLAS' && $result['entity_type'] == 'LESGROEP' ) ||
		( $entity_type == 'LESGROEP' && $result['entity_type'] == 'STAMKLAS'))) { // gevonden
		$entity_id .= ','.$result['entity_id'];
		$entity_name .= ','.$result['entity_name'];
		$entity_multiple = 1;
	}
}

// sorteer de gekozen entities als het er meer zijn
if ($entity_multiple) {
        $tmp = explode(',', $entity_name);
        sort($tmp);
        $entity_name = implode(',', $tmp);
}

switch ($entity_type) {
case 'LESGROEP':
case 'STAMKLAS':
	$lln_entity_ids = lln_query($entity_id, $rooster_version, $week_id);
	$data = master_query($lln_entity_ids, 'students', $rooster_version, $week_id);
	if ($entity_multiple) $type = 'groepen '.$entity_name;
	else $type = 'groep '.$entity_name;
	$type .= ' <span class="unknown">door nog ontbrekende informatie worden leerling- en groepsroosters mogelijk niet goed weergegven</span>';
	break;
case 'CATEGORIE':
	$group_entity_ids = db_single_field('SELECT GROUP_CONCAT(entity_id) FROM entity_zids WHERE sisy_id = ? AND bos_id = ? AND parent_entity_id IN ( '.$entity_id.' )', $sisy_id, $bos_id);
	$data = master_query($group_entity_ids, 'groups', $rooster_version, $week_id);
	if ($entity_multiple) $type = 'categorie&euml;n '.$entity_name;
	else $type = 'categorie '.$entity_name;
	break;
case 'LOKAAL':
	if ($entity_multiple) $type = 'lokalen '.$entity_name;
	else $type = 'lokaal '.$entity_name;
	$data = master_query($entity_id, 'locations', $rooster_version, $week_id);
	break;
case 'PERSOON':
	$isWhat = db_single_row("SELECT * FROM users WHERE entity_id = ?", $entity_id);
	if ($isWhat['isFamilyMember']) fatal("$entity_name is ouder, dat kan het systeem nog niet aan...");
	if ($isWhat['isEmployee'] && $isWhat['isStudent']) fatal("$entity_name is zowel docent als leerling, dat kan het systeem nog niet aan...");
	if ($isWhat['isStudent']) {
		$data = master_query($entity_id, 'students', $rooster_version, $week_id);
		if ($entity_multiple) $type = 'leerlingen '.$entity_name;
		else $type = 'leerling '.$entity_name;
		$type .= ' <span class="unknown">door nog ontbrekende informatie worden leerling- en groepsroosters mogelijk niet goed weergegven</span>';
	}
	if ($isWhat['isEmployee']) {
		$data = master_query($entity_id, 'teachers', $rooster_version, $week_id);
		if ($entity_multiple) $type = 'docenten '.$entity_name;
		else $type = 'docent '.$entity_name;
	}
	break;
case 'VAK':
	if ($entity_multiple) $type = 'vakken '.$entity_name;
	else $type = 'vak '.$entity_name;
	$data = master_query($entity_id, 'subjects', $rooster_version, $week_id);
	break;
case '*':
	$type = '*';
	$data = master_query(NULL, '', $rooster_version, $week_id);
	break;
default:
	fatal('onmogelijk type');
}

//db_dump_result($data);

$data = db_build_assoc_rekey($data);
//exit;

cont:

function make_link($target, $text = NULL) {
	global $link_tail, $link_tail_tail;
	return '<a href="?q='.urlencode($target).$link_tail.($text?$text:$target).'</a>';
}

function enccommabr($string) {
	$array = explode(',', $string);
	if (count($array) > 5) {
		$array = array_slice($array, 0, 4);
		$array[] = '&vellip;';
	}
        return implode('<br>', $array);
}

function vakmatch($vak, $match) {
	return preg_match("/\.{$vak}[0-9],/", $match.',');
}


function add(&$info, $name, $void = '') {
        global $entity_name;
        if ($name == $entity_name) return;
        if ($name == '') {
                if ($void) $info[] = $void;
        } else $info[] = make_link($name, enccommabr($name));

}

function add_lv(&$info, $lesgroepen, $vak) {
        global $entity_type, $entity_multiple;
        if ($entity_type == 'LEERLING' && !$entity_multiple) {
                if ($vak != '') $info[] = enccommabr($vak);
        } else {
                add($info, $lesgroepen);
                // we laten het vak alleen zien als het niet in de naam van de lesgroep zit
                if ($vak != '') {
                        foreach (explode(',', $vak) as $v) {
                                if (!vakmatch($v, $lesgroepen)) {
                                        $info[] = enccommabr($vak);
                                        break;
                                }
                        }
                }
        }
}

function print_diff($row) {
        if ($row['f_d'] != $row['s_d'] || $row['f_u'] != $row['s_u']) {
		//if ($row['f_d'] != $row['s_d'] /*&& $_GET['dy'] != '*'*/) $output[] = make_link($_GET['q'], print_dag($row['s_d']), $row['DAG2]).$row[UUR2];
                /*else*/ $output[] = isodayname($row['s_d']).$row['s_u'];
        }
        if ($row['f_groups'] != $row['s_groups'] && $row['s_groups'] != '') $output[] = make_link($row['s_groups']); //, NULL, $row['s_d']);
        if ($row['f_subjects'] != $row['s_subjects'] && $row['s_subjects'] != '') {
                if ($row['s_subjects'] != '' && !vakmatch($row['s_subjects'], $row['s_groups'])) $output[] = htmlenc($row['s_subjects']);
        }
        if ($row['f_teachers'] != $row['s_teachers']) {
                if ($row['s_teachers'] != '') $output[] = make_link($row['s_teachers']);//, NULL, $row[DAG2]);
                else $output[] = '<span class="unknown">DOC?</span>';
        }
        if ($row['f_locations'] != $row['s_locations']) {
                if ($row['s_locations'] != '') $output[] = make_link($row['s_locations']); //, NULL, $row[DAG2]);
                else $output[] = '<span class="unknown">LOK?</span>';
        }
        return implode('/', $output);
}

html_start($_SERVER['EZ_PORTAL_INSTITUTION'], <<<EOS
$(function(){
	// focus search box
	$('#q').focus();
	// autosubmit of a selectbox changes
	$('#select>select').change(function () { $('#select').submit(); });
});
EOS
); ?>
<p><div class="noprint" style="float: left">
<form id="search" method="GET" name="search" accept-charset="UTF-8">
<input type="submit" value="Zoek:">
<input id="q" placeholder="llnr, groep, /vak, afk, lok, categorie of *" size="40" name="q"><?php if ($_GET['q'] != '') { if ($entity_type === '') echo(' <span class="error">Zoekterm "'.htmlenc($_GET['q']).'" niet gevonden.</span>'); else echo(' of kijk in de '.make_link('', 'lijst').'.'); } ?>
<input type="hidden" name="bw" value="<?=$bw?>">
<?php if ($default_week == $safe_week) { ?>
<input type="hidden" name="wk" value="">
<?php } else { ?>
<input type="hidden" name="wk" value="<?=$safe_week?>">
<?php } ?>
</form>
</div>
<div class="noprint" style="float:right">
<form id="select" method="GET" name="basisweek" accept-charset="UTF-8">
<?php if ($prev_week) { ?>
<a href="?q=<?=urlencode($_GET['q']).$link_tail_wowk.$prev_week.$link_tail_tail?>">&lt;</a>
<?php } else { ?>
<del>&lt;</del>
<?php } ?>
<select autocomplete="off" name="wk">
<?=$week_options?>
</select>
<?php if ($next_week) { ?>
<a href="?q=<?=urlencode($_GET['q']).$link_tail_wowk.$next_week.$link_tail_tail?>">&gt;</a>
<?php } else { ?>
<del>&gt;</del>
<?php } ?>
<select autocomplete="off" name="bw">
<option <?=($bw == 'b')?'selected ':''?>value="b">basisrooster</option>
<option <?=($bw == 'w')?'selected ':''?>value="w">weekrooster</option>
</select>
<input name="q" type="hidden" value="<?=htmlenc($_GET['q'])?>">
</form>
</div>
<div style="clear: both">
</div>
<?php if ($entity_type === '') { ?>
<p>Selecteer hieronder een klas, docent, lokaal, vak of categorie:
<p>Klassen:
<?php foreach ($res_klas as $entity_name) { echo(' '.make_link($entity_name)); }; ?>
<p>Docenten:
<?php foreach ($res_doc as $entity_name) { echo(' '.make_link($entity_name)); }; ?>
<p>Lokalen:
<?php foreach ($res_lok as $entity_name) { echo(' '.make_link($entity_name)); }; ?>
<p>Vakken:
<?php foreach ($res_vak as $entity_name) { echo(' '.make_link($entity_name, substr($entity_name, 1))); }; ?>
<p>Categorie&euml;n:
<?php foreach ($res_cat as $entity_name) { echo(' '.make_link($entity_name)); }; ?>
<?php
} else {
	?><p><?php
	if ($_GET['q']) {
		if ($bw == 'b') echo('Basisrooster');
		else echo('Weekrooster');
		?> van <?=$type?><?php
	} 
?>
<p><table id="rooster"><tr><th></th>
<th>ma <?php echo date("j-n", $thismonday)          ?></th>
<th>di <?php echo date("j-n", $thismonday +  86400) ?></th>
<th>wo <?php echo date("j-n", $thismonday + 172800) ?></th>
<th>do <?php echo date("j-n", $thismonday + 259200) ?></th>
<th>vr <?php echo date("j-n", $thismonday + 345600) ?></th>
</tr>
<?php 
	$les = next($data);
	for ($i = 1; $i <= config('MAX_LESUUR'); $i++) { ?>
<tr class="spacer"><td><?=$i?></td>
<?php 		for ($j = 1; $j <= 5; $j++) { ?>
<td><?php		while ($les && $les['f_d'] == $j && $les['f_u'] == $i) {
				/* here we decide if and how the lesson is displayed */
				if (show_cancelled($les, $bw)) {
					// deze les is uitgevallen
					$extra = ' uitval';
					$comment = '(uitval)';
				} else if (show_new($les, $bw)) {
					// deze les is extra
					$extra = ' extra';
					$comment = '(extra)';
				} else if (show_replaced($les, $bw)) {
					// als:
					// - de vervangende les zichtbaar is
					// - op hetzelfde uur staat als de vervangende les
					// (als deze les hetzelfde is als de vervangede les
					// dan is automatisch aan deze twee voorwaarden voldaan)
					// ---> toon deze invalid les dan niet
					if (isset($data[$les['s_id']]) &&
							$les['f_d'] == $les['s_d'] &&
							$les['f_u'] == $les['s_u']) {
						$les = next($data);
						continue;
					}
					// in andere gevallen: toon de les als verplaatstnaar
					$extra = ' verplaatstnaar';
					$comment = '(naar '.print_diff($les).')';
				} else if (show_replacement($les, $bw)) {
					// deze les is het de nieuwe les die bij een verplaatste
					// les hoort, als:
					// - als de oorspronkelijke les ook zichtbaar is
					//   (als in: in dit rooster hoort, hij is al wel verborgen)
					// - op hetzelfde uur staat als de oorspronkelijke les
					// ----> toon deze les dan in geel
					if (isset($data[$les['s_id']]) &&
							$les['f_d'] == $les['s_d'] &&
							$les['f_u'] == $les['s_u']) {
						$extra = ' gewijzigd';
						$comment = '(was '.print_diff($les).')';
					} else {
						$extra = ' verplaatstvan';
						$comment = '(van '.print_diff($les).')';
					}
				} else if (show_normal($les, $bw)) {
					// als de les nu nog niet aan de orde is geweest,
					// dan is het een normal les!
					$extra = '';
					$comment = '';
				} else {
					// do not show
					$les = next($data);
					continue;
				}
				$info = array();
				add_lv($info, $les['f_groups'], $les['f_subjects']);
				add($info, $les['f_teachers']);
				add($info, $les['f_locations']); ?>
<div class="les<?=$extra?>">
<?php				if (count($info)) { ?>
<table title="<?=$les['zid']?>">
<tr><td><?=implode("</td>\n<td>/</td>\n<td>", $info)?></td></tr>
</table>
<?php			 	}
				if ($comment) { ?>
<div class="comment"><?=$comment?></div>
<?php				} ?>
<div class="clear"></div></div>
<?php				$les = next($data);
			}
?></td>
<?php		 } ?>
</tr>
<?php	}?>
</table>
<?php if ($bw == 'w') { ?>
<div class="noprint small">Kleurcodes:
<span class="legenda uitval">&nbsp;</span>&nbsp;uitval,
<span class="legenda gewijzigd">&nbsp;</span>&nbsp;gewijzigd,
<span class="legenda extra">&nbsp;</span>&nbsp;nieuw,
<span class="legenda verplaatstvan">&nbsp;</span>&nbsp;verplaatst van,
<span class="legenda verplaatstnaar">&nbsp;</span>&nbsp;verplaatst naar,
<span class="legenda vrijstelling">&nbsp;</span>&nbsp;vrijstelling.
</div>
<?php }
}
?>
<span id="updateinfo">
Het rooster in deze week r<?=$rooster_version?>,
laatste wijziging <?=$rooster_info['last_modified']?>,
laatste synchronisatie <?=$rooster_info['last_synced']?>.
Als je je toegangscookie verwijdert, dan moet je opniew een koppelcode invoeren
om toegang te krijgen tot het roosterbord.
<a href="forget_access_token.php">[cookie van <?=$access_info['entity_name']?> verwijderen]</a>
</span>
<?php html_end(); ?>
