<?php

require_once('common.php');
require_once('html.php');

$access_info = get_access_info();

if (!$access_info) {
	html_start(); ?>
	Als je toegang hebt tot <?=$_SERVER['EZ_PORTAL_INSTITUTION']?>.zportal.nl, dan kun je daar inloggen een koppelcode van 12 cijfers aanmaken. Deze code kun je dan hieronder invullen om het rooster te kunnen raadplegen.

<p>
<form action="req_access_token.php" method="POST" accept-charset="UTF-8">
instelling: ovc
<p><label>code: <input autocomplete="off" type="text" placeholder="*** *** *** ***" name="code"></label>
<p><input type="submit" value="Koppel">
</form>	
<p>Als de koppeling lukt, dan wordt er een cookie met de naam <code>access_token</code> geplaatst in je browser. Dit cookie zorgt ervoor dat je steeds, zonder inloggen of koppelen, bij het rooster kunt. In de website heb je altijd de mogelijkheid om het cookie te verwijderen. Je gaat vanzelfsprekend akkoord met het plaatsen van dit cookie.
<?php	html_end();
	exit;
}

require_once('zportal.php');
zportal_set_access_token($access_info['access_token']);
$sisyinfo = get_sisyinfo();

// sanitize input
if (!isset($_GET['bw']) || ( $_GET['bw'] != 'w' && $_GET['bw'] != 'b' )) $_GET['bw'] = 'w';

$bw = $_GET['bw'];

// now + maandag - vrijdag eind van het 9e uur (16:50 op het ovc)
$offset = $_SERVER['REQUEST_TIME'] + 2*24*60*60 + (10 + 7*60)*60;
$current_week = date('W', $offset);
$current_year = date('o', $offset);
$weeks = db_all_assoc_rekey("SELECT * FROM weeks WHERE sisy_id = ?", $sisyinfo['sisy_id']);
$default_week_info = db_single_row(<<<EOQ
SELECT week_id, week FROM weeks WHERE year > ? OR ( year = ? AND week >= ? ) ORDER BY year, week
EOQ
, $current_year, $current_year, $current_week);
$default_week = $default_week_info['week'];
$default_week_index = $default_week_info['week_id'];

if (!isset($_GET['wk'])) $_GET['wk'] = $default_week;

$week_index = 0;
foreach ($weeks as $idx => $week) {
	if ($_GET['wk'] == $week['week']) {
		$week_index = $idx;
		$safe_week = $week['week'];
		break;
	}
}

if (!$week_index) {
	$safe_week = $default_week;
	$week_index = $default_week_index;
}

$thismonday = $weeks[$week_index]['monday_unix_timestamp'];

$week_options = '';
$prev_week = NULL;
$next_week = NULL;
$last_week = NULL;
foreach ($weeks as $week) {
	$week_options .= '<option';
	if ($last_week === NULL && $prev_week != NULL) $next_week = $week['week'];
	if ($week['week'] == $safe_week) {
		$prev_week = $last_week;
		$week_options .= ' selected';
		$last_week = NULL;
	} else $last_week = $week['week'];
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

if (count($qs) > 1) fatal("multiple search terms currently not supported");

$result = db_single_row("SELECT * FROM entities WHERE entity_name = ? AND entity_type != 'VAK'", $qs[0]);
if (!$result) {
	$safe_id = '';
	$entity_type = '';
	$entity_name = '';
	$type = '';
	$res_klas = db_all_assoc_rekey("SELECT entity_id, entity_name FROM entities WHERE entity_type = 'STAMKLAS' AND entity_visible ORDER BY entity_name");
	$res_doc = db_all_assoc_rekey("SELECT entity_id, entity_name FROM entities JOIN users USING (entity_id) WHERE entity_type = 'PERSOON' AND isEmployee AND entity_visible ORDER BY entity_name");
	$res_lok = db_all_assoc_rekey("SELECT entity_id, entity_name FROM entities WHERE entity_type = 'LOKAAL' AND entity_visible ORDER BY entity_name");
	$res_cat = db_all_assoc_rekey("SELECT entity_id, entity_name FROM entities WHERE entity_type = 'CATEGORIE' AND entity_visible ORDER BY entity_name");
	//$res_vak = db_all_assoc_rekey("SELECT entity_name FROM entities WHERE entity_type = 'VAK' AND entity_visible");

	goto cont;
}

$entity_type = $result['entity_type'];
$entity_name = $result['entity_name'];
$safe_id = $result['entity_id'];

$fields = 'id,appointmentInstance,start,startTimeSlot,endTimeSlot,teachers,groups,locations,valid,cancelled,new,changeDescription,subjects,teacherChanged,groupChanged,locationChanged,timeChanged,students,schedulerRemark,modified,moved';

switch ($entity_type) {
case 'LESGROEP':
	$type = 'lesgroep '.$entity_name;
	$appointments = zportal_GET_data('appointments', 'start', $thismonday,
		'end', $thismonday + 7*24*60*60, 'schoolInSchoolYear', config('SISY'),
		'containsStudentsFromGroupInDepartment', $result['entity_zid'], 'type', 'lesson');
	break;
case 'STAMKLAS':
	$type = 'klas'.$entity_name;
	$type = 'lesgroep '.$entity_name;
	$appointments = zportal_GET_data('appointments', 'start', $thismonday,
		'end', $thismonday + 7*24*60*60, 'schoolInSchoolYear', config('SISY'),
		'containsStudentsFromGroupInDepartment', $result['entity_zid'], 'type', 'lesson');
	break;
case 'CATEGORIE':
	$type = 'categorie '.$entity_name;
	$appointments = zportal_GET_data('appointments', 'start', $thismonday,
		'end', $thismonday + 7*24*60*60, 'departmentOfBranch', $result['entity_zid'], 'type', 'lesson');
	break;
case 'LOKAAL':
	//print_r($result);
	//echo("thismonday=$thismonday, nextmonday=".($thismonday+7*24*60*60));
	$type = 'lokaal '.$entity_name;
	$appointments = zportal_GET_data('appointments', 'start', $thismonday,
		'end', $thismonday + 7*24*60*60, 'locationsOfBranch', $result['entity_zid'], 'type', 'lesson');
	break;
case 'PERSOON':
	$type = $entity_name;
	$appointments = zportal_GET_data('appointments', 'start', $thismonday,
		'end', $thismonday + 7*24*60*60, 'user', $result['entity_name'], 'type', 'lesson', 'fields', $fields);
	break;
	/* not supported by zermelo api (if requesting as normal user, at least)
case 'VAK':
	$type = 'vak '.$entity_name;
	$appointments = zportal_GET_data('appointments', 'start', $thismonday, 'end', $thismonday + 7*24*60*60, 'subjects', 'wisa');
	break;
	 */
default:
	fatal('onmogelijk type');
}

cont:

function make_link($target, $text = NULL) {
	global $link_tail, $link_tail_tail;
	return '<a href="?q='.urlencode($target).$link_tail.($text?$text:htmlenc($target)).'</a>';
}

function enccommabr($string) {
        return implode('<br>', explode(',', htmlenc($string)));
}

function vakmatch($vak, $match) {
	return preg_match("/\.{$vak}[0-9]\$/", $match);
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
        if ($entity_type == LEERLING && !$entity_multiple) {
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

html_start(dereference($sisyinfo, 'sisy_name'), <<<EOS
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
<input id="q" size="40" name="q"><? if ($_GET['q'] != '') { if ($entity_type === '') echo(' <span class="error">Zoekterm "'.htmlenc($_GET['q']).'" niet gevonden.</span>'); else echo(' of kijk in de '.make_link('', 'lijst').'.'); } ?>
<input type="hidden" name="bw" value="<?=$bw?>">
<?php if ($default_week == $safe_week) { ?>
<input type="hidden" name="wk" value="">
<? } else { ?>
<input type="hidden" name="wk" value="<?=$safe_week?>">
<? } ?>
</form>
</div>
<div class="noprint" style="float:right">
<form id="select" method="GET" name="basisweek" accept-charset="UTF-8">
<?php if ($prev_week) { ?>
<a href="?q=<?=urlencode($_GET['q']).$link_tail_wowk.$prev_week.$link_tail_tail?>">&lt;</a>
<? } else { ?>
<del>&lt;</del>
<? } ?>
<select autocomplete="off" name="wk">
<?=$week_options?>
</select>
<?php if ($next_week) { ?>
<a href="?q=<?=urlencode($_GET['q']).$link_tail_wowk.$next_week.$link_tail_tail?>">&gt;</a>
<? } else { ?>
<del>&gt;</del>
<? } ?>
<select autocomplete="off" name="bw">
<option <?=($bw == 'b')?'selected ':''?>value="b">basisrooster</option>
<!--<option <?=($bw == 'w')?'selected ':''?>value="w">weekrooster</option>-->
</select>
<input name="q" type="hidden" value="<? echo(htmlenc($_GET['q'])) ?>">
</form>
</div>
<div style="clear: both">
</div>
<?php if ($entity_type === '') { ?>
<p>Selecteer hieronder een klas, docent, lokaal of categorie:
<p>Klassen:
<?php foreach ($res_klas as $entity_name) { echo(' '.make_link($entity_name)); }; ?>
<p>Docenten:
<?php foreach ($res_doc as $entity_name) { echo(' '.make_link($entity_name)); }; ?>
<p>Lokalen:
<?php foreach ($res_lok as $entity_name) { echo(' '.make_link($entity_name)); }; ?>
<p>Categorie&euml;n:
<?php foreach ($res_cat as $entity_name) { echo(' '.make_link($entity_name)); }; ?>
<?
} else {
	?><p><?php
	if ($_GET['q']) {
		if ($bw == 'b') echo('Basisrooster');
		else echo('Weekrooster');
		?> van <? echo($type.'.');
	} 
	$totable = array();
	if (isset($appointments)) foreach ($appointments as $appointment) {

		$uur = dereference($appointment, 'startTimeSlot');
		if ($uur != dereference($appointment, 'endTimeSlot')) fatal('start and end timeslot not the same for lesson');
		$day_number = date('N', dereference($appointment, 'start'));
		$sort = $uur.$day_number;
		$dag = isodayname($day_number);
		$valid = dereference($appointment, 'valid');
		$cancelled = dereference($appointment, 'cancelled');
		$modified = dereference($appointment, 'modified');
		$moved = dereference($appointment, 'moved');
		//$timeChanged = $appointment['timeChanged']; //dereference($appointment, 'timeChanged');
		//$locationChanged = dereference($appointment, 'locationChanged');
		//$groupChanged = dereference($appointment, 'groupChanged');
		//$teacherChanged = dereference($appointment, 'teacherChanged');
		if (!($modified||$moved/*$timeChanged||$locationChanged||$groupChanged||$teacherChanged*/)) {
			
			if (!array_key_exists($sort, $totable)) $totable[$sort] = array();
			sort($appointment['teachers']);
			$totable[$sort][] = array (
				'dag' => $dag,
				'uur' => $uur,
				'groups' => implode(',',$appointment['groups']),
				'subjects' => implode(',',$appointment['subjects']),
				'teachers' => implode(',',$appointment['teachers']),
				'locations' => implode(',',$appointment['locations'])
			);
		}
		

?>
	<!--<pre><?=$appointment['appointmentInstance'].'-'.$appointment['id'].'-'.$uur.$day_number.'-'.$dag.$uur.'-'.implode(',',$appointment['groups']).'-'.implode(',',$appointment['subjects']).'-'.implode(',',$appointment['teachers']).'-'.implode(',',$appointment['locations']).'-'.($appointment['valid']?'valid':'notvalid').'-'.($appointment['cancelled']?'cancelled':'notcancelled')?><? print_r($appointment); ?></pre>-->
<? 	}
ksort($totable);
?>
<p><table id="rooster"><tr><th></th>
<th>ma <? echo date("j-n", $thismonday)          ?></th>
<th>di <? echo date("j-n", $thismonday +  86400) ?></th>
<th>wo <? echo date("j-n", $thismonday + 172800) ?></th>
<th>do <? echo date("j-n", $thismonday + 259200) ?></th>
<th>vr <? echo date("j-n", $thismonday + 345600) ?></th>
</tr>
<? for ($i = 1; $i <= config('MAX_LESUUR'); $i++) { ?>
<tr class="spacer"><td><?=$i?></td>
<? for ($j = 1; $j <= 5; $j++) { ?>
<td><?
$key = $i.$j;
if (!array_key_exists($key, $totable)) continue;
foreach($totable[$key] as $les) {
	$info = array();
	add_lv($info, $les['groups'], $les['subjects']);
	add($info, $les['teachers']);
	add($info, $les['locations']);
	echo('<div class="les'.$extra.'">');
	if (count($info)) echo('<table><tr><td>'.implode('</td><td>/</td><td>', $info).'</td></tr></table>');
	echo('<div class="clear"></div></div>');
	//print_r($les);
}
?></td>
<?php } ?>
</tr>
<?php }?>
</table>
<?php if (false && $bw == 'w') { ?>
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

<p>
<span id="updateinfo">
Je browser heeft een cookie, waarmee je autmatisch toegang hebt tot het roosterbord als <?=$access_info['entity_name']?>. Als je het cookie verwijdert, dan moet je opniew een koppelcode invoeren om toegang te krijgen tot het roosterbord. <a href="forget_access_token.php">[cookie verwijderen]</a>
</span>

<?php html_end(); ?>
