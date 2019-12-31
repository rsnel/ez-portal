<?php

function html_start($title = '', $script = '') {
	global $voxdb;
	if ($title != '') $title .= ' - ';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<link rel="stylesheet" href="css/style.css">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="manifest" href="/site.webmanifest">
<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">
<meta name="msapplication-TileColor" content="#da532c">
<meta name="theme-color" content="#ffffff">
<script src="js/jquery-3.4.1.min.js"></script>
<?php if ($script) { ?>
<script>
//<![CDATA[
<?=$script?>
//]]>
</script>
<?php } ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=$title?> ez-portal</title>
</head>
<body>
<div class="flex-wrapper">
<div class="container">
<div style="clear: both"><?php
}

function html_end() {
	$version_copy = '-'.exec('git describe').' &copy; '.substr(exec('git show -s --format=%ci'), 0, 4);
?></div>
</div>
<div id="footer">
<div id="footerlogo">
<img alt="AGPLv3 logo" src="images/AGPLv3_Logo.svg">
</div>
<div id="footertext">
<a href="https://ez-portal.nl/">ez-portal</a><?=$version_copy?> Rik Snel &lt;rik@snel.it&gt;, Favicons (<a href="https://commons.wikimedia.org/wiki/File:Ernst_Zermelo_1900s.jpg">source</a>) are in the public domain.<br>
Released as <a href="http://www.gnu.org/philosophy/free-sw.html">free software</a> without warranties under <a href="http://www.fsf.org/licensing/licenses/agpl-3.0.html">GNU AGPL v3</a>.  Powered by PHP <?=phpversion()?>.<br>
Sourcecode: git clone <a href="https://github.com/rsnel/ez-portal/">https://github.com/rsnel/ez-portal/</a>
</div>
</div>
</div>
</body>
</html>
<?php }
?>
