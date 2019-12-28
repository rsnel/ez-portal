<?

function fatal_curl() {
	global $ch;
	$errno = curl_errno($ch);
        fatal('cURL error '.$errno.': '.curl_strerror($errno).':'.curl_error($ch));
}

if (!$ch = curl_init()) fatal('error initializing cURL');

if (!curl_setopt($ch, CURLOPT_RETURNTRANSFER, true))
	fatal_curl();

function zportal_set_access_token($access_token) {
	global $ch;
	if (!curl_setopt($ch, CURLOPT_USERPWD, 'Bearer:'.$access_token))
		fatal_curl();
}

function zportal_vquery_string($args) {
	$length = count($args);

	if (!$length) return '';
	else if ($length%2) fatal('get_query_string needs complete name=value pairs');

	$query = array();
	$mode = 0;

	foreach ($args as $arg) {
                if ($mode == 0) {
                        $key = urlencode($arg);
                } else if ($mode == 1) {
			if (is_array($arg)) $query[] = $key.'='.urlencode(implode(',', $arg));
			else $query[] = $key.'='.urlencode($arg);
                } else fatal('impossible value for $mode');
                $mode = 1 - $mode;
        }

	return implode('&', $query);
}

function zportal_query_string() {
	return zportal_vquery_string(func_get_args());
}

function zportal_url($command, $query_string = '') {
	$common_url = '.zportal.nl/api/v3/';

	if ($query_string != '') $command .= '?';

	return 'https://'.$_SERVER['EZ_PORTAL_INSTITUTION'].$common_url.$command.$query_string;
}

function zportal_json($url) {
	global $ch;

	if (!curl_setopt($ch, CURLOPT_URL, $url)) fatal_curl();

	if (($ret = curl_exec($ch)) === false) fatal_curl();

	if (!($httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE))) fatal_curl();

	if ($httpcode != 200) fatal("got HTTP code $httpcode from portal: $ret, URL: $url");

	if (($json = json_decode($ret, true)) == NULL) fatal("unable to decode JSON data");

	file_put_contents(config('DATADIR').'cache_'.hash('sha256', $url).'.json', $ret);

	return $json;
}

function zportal_json_cached($url) {
	$filename = config('DATADIR').'cache_'.hash('sha256', $url).'.json';
	if (is_readable($filename) && ($ret = file_get_contents($filename)) !== FALSE) {
		echo("cache hit!\n");
		if (($json = json_decode($ret, true)) == NULL) fatal("unable to decode JSON data");
		return $json;
	}

	return zportal_json($url);
}

function zportal_vPOST_json($command, $args) {
	global $ch;

	$query_string = zportal_vquery_string($args);

	$url = zportal_url($command);

	if (!curl_setopt($ch, CURLOPT_POST, true)) fatal_curl();

	if (!curl_setopt($ch, CURLOPT_POSTFIELDS, $query_string))
        	fatal_curl();

	return zportal_json($url);
}

function zportal_vGET_json($command, $args) {
	global $ch;

	$query_string = zportal_vquery_string($args);

	$url = zportal_url($command, $query_string);

	if (!curl_setopt($ch, CURLOPT_HTTPGET, true)) fatal_curl();

	return zportal_json($url);
}

function zportal_vGET_json_cached($command, $args) {
	global $ch;

	$query_string = zportal_vquery_string($args);

	$url = zportal_url($command, $query_string);

	if (!curl_setopt($ch, CURLOPT_HTTPGET, true)) fatal_curl();

	return zportal_json_cached($url);
}

function zportal_vGET_data($command, $args) {
	global $ch;

	$json = zportal_vGET_json($command, $args);

	return dereference($json, 'response', 'data');
}

function zportal_vGET_data_cached($command, $args) {
	global $ch;

	$json = zportal_vGET_json_cached($command, $args);

	return dereference($json, 'response', 'data');
}

function zportal_vGET_row($command, $args) {
	return dereference(zportal_vGET_data($command, $args), 0);
}

function zportal_GET_data($command) {
	global $ch;

	/* get args after $command */
	$args = func_get_args();
        array_shift($args);

	return zportal_vGET_data($command, $args);
}

function zportal_GET_data_cached($command) {
	global $ch;

	/* get args after $command */
	$args = func_get_args();
        array_shift($args);

	return zportal_vGET_data_cached($command, $args);
}

function zportal_GET_row($command) {
	global $ch;

	/* get args after $command */
	$args = func_get_args();
        array_shift($args);

	return zportal_vGET_row($command, $args);
}

function zportal_GET_json($command) {
	global $ch;

	/* get args after $command */
	$args = func_get_args();
        array_shift($args);

	return zportal_vGET_json($command, $args);
}

function zportal_POST_json($command) {
	global $ch;

	/* get args after $command */
	$args = func_get_args();
        array_shift($args);

	return zportal_vPOST_json($command, $args);
}

?>
