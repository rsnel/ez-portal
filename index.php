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

if (!isset($_COOKIE['access_token'])) {
	fatal("auth_token cookie not set");
}

html_start(); ?>
toegang tot het roosterbord als <?=$access_info['access_code']?> <a href="forget_access_token.php">[cookie verwijderen]</a> (na het vergeten van het cookie, moet je opnieuw een koppelcode maken en invoeren om toegang te krijgen tot het roosterbord)

<p>Meer functionaliteit (naast het koppelen met de koppelcode), zoals het tonen van het rooster, is nog niet geimplementeerd.

<?php

html_end();

//print_r($_SERVER);

//print_r($_COOKIE);

//setcookie('auth_token', 'bla', time() - 3600, '/', $_SERVER['HTTP_HOST'], true, true);

//setcookie('auth_token', 'bladibla');

?>
