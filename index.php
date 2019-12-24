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

// now + maandag - vrijdag eind van het 9e uur
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

if (!isset($_GET['q'])) $_GET['q'] = '';
else $_GET['q'] = trim($_GET['q']);

$qs = explode(',', $_GET['q']);

if (count($qs) > 1) fatal("multiple search terms currently not supported");

$result = db_single_row("SELECT * FROM entities WHERE entity_name = ?", $qs[0]);
if (!$result) {
	$safe_id = '';
	$entity_type = '';
	$entity_name = '';
	$type = '';
	$res_klas = db_all_assoc_rekey("SELECT entity_id, entity_name FROM entities WHERE entity_type = 'STAMKLAS' AND entity_visible ORDER BY entity_name");
	$res_doc = db_all_assoc_rekey("SELECT entity_id, entity_name FROM entities JOIN users USING (entity_id) WHERE entity_type = 'PERSOON' AND isEmployee AND entity_visible ORDER BY entity_name");
	$res_lok = db_all_assoc_rekey("SELECT entity_id, entity_name FROM entities WHERE entity_type = 'LOKAAL' AND entity_visible ORDER BY entity_name");
	//$res_vak = db_all_assoc_rekey("SELECT entity_name FROM entities WHERE entity_type = 'VAK' AND entity_visible");
	$res_cat = db_all_assoc_rekey("SELECT entity_id, entity_name FROM entities WHERE entity_type = 'CATEGORIE' AND entity_visible ORDER BY entity_name");

	goto cont;
}

$entity_type = $result['entity_type'];
$entity_name = $result['entity_name'];
$safe_id = $result['entity_id'];

switch ($entity_type) {
case 'LESGROEP':
	$type = 'lesgroep '.$entity_name;
	break;
case 'STAMKLAS':
	$type = 'klas'.$entity_name;
	break;
case 'CATEGORIE':
	$type = 'categorie '.$entity_name;
	break;
case 'LOKAAL':
	print_r($result);
	echo("thismonday=$thismonday, nextmonday=".($thismonday+7*24*60*60));
	$type = 'lokaal '.$entity_name;
	$appointments = zportal_GET_data('appointments', 'start', $thismonday, 'end', $thismonday + 7*24*60*60, 'locationsOfBranch', $result['entity_zid']);
	break;
case 'VAK':
	$type = 'vak '.$entity_name;
	break;
case 'PERSOON':
	$type = $entity_name;
	break;
default:
	fatal('onmogelijk type');
}

cont:

function make_link($target, $text = NULL) {
	global $link_tail, $link_tail_tail;
	return '<a href="?q='.urlencode($target).$link_tail.($text?$text:htmlenc($target)).'</a>';
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
<option <?=($bw == 'w')?'selected ':''?>value="w">weekrooster</option>
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
	if (isset($appointments)) { ?>
		<pre><? print_r($appointments); ?></pre>
<? }
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
<td></td>
<? } ?>
</tr>
<? }?>
</table>
<div class="noprint small">Kleurcodes:
<span class="legenda uitval">&nbsp;</span>&nbsp;uitval,
<span class="legenda gewijzigd">&nbsp;</span>&nbsp;gewijzigd,
<span class="legenda extra">&nbsp;</span>&nbsp;nieuw,
<span class="legenda verplaatstvan">&nbsp;</span>&nbsp;verplaatst van,
<span class="legenda verplaatstnaar">&nbsp;</span>&nbsp;verplaatst naar,
<span class="legenda vrijstelling">&nbsp;</span>&nbsp;vrijstelling.
</div>
<? 
}
?>

<p>toegang tot het roosterbord als <?=db_single_field("SELECT entity_name FROM entities JOIN access USING (entity_id) WHERE access_id = ?", $access_info['access_id'])?> <a href="forget_access_token.php">[cookie verwijderen]</a> (na het vergeten van het cookie, moet je opnieuw een koppelcode maken en invoeren om toegang te krijgen tot het roosterbord)

<p>Meer functionaliteit (naast het koppelen met de koppelcode), zoals het tonen van het rooster, is nog niet geimplementeerd.

<?php

html_end();

//print_r($_SERVER);

//print_r($_COOKIE);

//setcookie('auth_token', 'bla', time() - 3600, '/', $_SERVER['HTTP_HOST'], true, true);

//setcookie('auth_token', 'bladibla');

?>
