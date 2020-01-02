<?php

require_once('common.php');
require_once('html.php');

$access_info = get_access_info();

html_start($_SERVER['EZ_PORTAL_INSTITUTION']); ?>

Weet het zeker? Als je je toegangscookie verwijdert, dan heb je pas weer toegang tot het roosterbord via
deze pagina als je opnieuw een koppelcode invoert.

<p><a href="do_forget_access_token.php">[cookie verwijderen]</a>

<?php html_end(); ?>
