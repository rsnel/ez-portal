<?php

if (!checksetarray($mysqli_info, 'host', 'username', 'passwd', 'dbname'))
	fatal('$mysql_info array not defined or complete '.
			'(host, username, passwd, dbane) in '.$configfile);

if (!($GLOBALS['db'] = mysqli_connect($mysqli_info['host'],
		$mysqli_info['username'], $mysqli_info['passwd'],
		$mysqli_info['dbname']))) {
	fatal("mysqli_connect (".mysqli_connect_errno()."): ".
			mysqli_connect_error());
}

function fatal_mysqli($function_name) {
	fatal($function_name.' ('.mysqli_errno($GLOBALS['db']).'): '.mysqli_error($GLOBALS['db']));
}

if (!mysqli_set_charset($db, "utf8"))
	fatal_mysqli('mysqli_set_charset');

// create & execute statement
function db_vce_stmt($query, $args) {
	$refs = array();

	if (!($refs[0] = mysqli_prepare($GLOBALS['db'], $query))) fatal_mysqli('msqli_prepare');
	$refs[1] = '';

	if (mysqli_stmt_param_count($refs[0]) != count($args))
		fatal('prepared statement expects '.mysqli_stmt_param_count($refs[0]).' parameter(s) but gets '.count($args).' parameter(s): '.$query);

	// bind parameters if there are any
	if (count($args)) {
		foreach ($args as &$arg) {
			$refs[] = &$arg;
			$type = gettype($arg);
			switch ($type) {
				case 'boolean':
				case 'integer':
					$refs[1] .= 'i';
					break;
				case 'string':
					$refs[1] .= 's';
					break;
				case 'NULL':
					$refs[1] .= 'i';
					break;
				default:
					fatal('unsupported type ('.$type.') for MySQL query (only integer, boolean, NULL and string supported)');
			}
		}

		if (!call_user_func_array('mysqli_stmt_bind_param', $refs)) fatal_mysqli('mysqli_bind_param');
	}

	if (!mysqli_stmt_execute($refs[0])) fatal_mysqli('mysqli_stmt_execute');

	return $refs[0];
}

function db_ce_stmt($query) {
	// get all arguments, and discard the first
	$args = func_get_args();
	array_shift($args);

	return db_vce_stmt($query, $args);
}

function db_vquery($query, $args) {
	$stmt = db_vce_stmt($query, $args);

	if (!($res = mysqli_stmt_get_result($stmt)))
		fatal_mysqli('mysqli_stmt_get_result');

	if (!mysqli_stmt_close($stmt))
		fatal_mysqli('mysqli_stmt_close');

	return $res;
}

function db_query($query) {
	// get all arguments, and discard the first
	$args = func_get_args();
	array_shift($args);

	return db_vquery($query, $args);

}

function db_direct($query) {
	if (!(mysqli_query($GLOBALS['db'], $query))) fatal_mysqli('mysqli_query');
}

function db_vall_assoc_rekey($query, $args) {
	$res = db_vquery($query, $args);

	$out = array();
	while ($row = mysqli_fetch_assoc($res)) {
		$key = array_shift($row);
		if (count($row) == 1) {
			$out[$key] = array_shift($row);
		} else $out[$key] = $row;
	}

	return $out;
}

function db_all_assoc_rekey($query) {
	// get all arguments, and discard the first
	$args = func_get_args();
	array_shift($args);

	return db_vall_assoc_rekey($query, $args);
}

function db_vsingle_row($query, $args) {
	$res = db_vquery($query.' LIMIT 1', $args);

	$row = mysqli_fetch_assoc($res);

	mysqli_free_result($res);

	return $row;
}

function db_single_row($query) {
	// get all arguments, and discard the first
	$args = func_get_args();
	array_shift($args);

	return db_vsingle_row($query, $args);
}

function db_vsingle_field($query, $args) {
	$row = db_vsingle_row($query, $args);

	if (!is_array($row)) return false; // no row

	if (!count($row)) fatal('first element of result row array not set?!?!?');

	// reset returns the first element of the array
	return reset($row);
}

function db_single_field($query) {
	// get all arguments, and discard the first
	$args = func_get_args();
	array_shift($args);

	return db_vsingle_field($query, $args);
}

function db_vexec($query, $args) {
	$stmt = db_vce_stmt($query, $args);

	if (($affected_rows = mysqli_stmt_affected_rows($stmt)) < 0)
		fatal_mysqli('mysqli_affected_rows');

	mysqli_stmt_close($stmt);

	return $affected_rows;
}

function db_exec($query) {
	$args = func_get_args();
	array_shift($args);

	return db_vexec($query, $args);
}

function db_get_id($id_name, $table) {
	$args = func_get_args();
	array_shift($args);
	array_shift($args);

	$argc = count($args);

	if ($argc%2) fatal('number of args to db_get_id after $table must be even');

	// build insert and select queries
	$insert = $id_name.' = NULL';
	$select = 'TRUE';
	$values = array();

	for ($i = 0; $i < $argc; $i += 2) {
		$insert .= ', '.$args[$i].' = ?';
		$select .= ' AND '.$args[$i].' = ?';
		$values[] = &$args[$i+1];
	}

	db_direct("LOCK TABLES $table WRITE");
	$id = db_vsingle_field('SELECT '.$id_name.' FROM '.$table.' WHERE '.$select, $values);
	if (!$id) {
		if (db_vexec('INSERT INTO '.$table.' SET '.$insert, $values) != 1) 
			fatal('expected INSERT to affect precisely 1 row, but it did not');
		$id = mysqli_insert_id($GLOBALS['db']);
	} 
	db_direct('UNLOCK TABLES');

	return $id;
}

function db_last_insert_id() {
        return mysqli_insert_id($GLOBALS['db']);
}

function from_unixtime($time) {
	return db_single_field("SELECT FROM_UNIXTIME(?)", $time);
}

function db_dump_result($res, $show_table_names = 0) {
	echo("<table>\n<thead>\n<tr>");

	while (($finfo = $res->fetch_field())) {
		echo('<th>');
		if ($show_table_names) echo($finfo->table.'<br>');
		echo($finfo->name.'</th>');
	}
	echo("</thead>\n<tbody>\n");

	while (($row = mysqli_fetch_array($res, MYSQLI_NUM))) {
		echo('<tr>');
		foreach ($row as $data) {
			if ($data === NULL) echo('<td><i>NULL</i></td>');
			else echo('<td>'.$data.'</td>');
		}
		echo("<tr>\n");
	}
	echo("</tbody>\n");
	echo("</table>\n");

	// reset result, so that it can be traversed again
	mysqli_field_seek($res, 0);
	mysqli_data_seek($res, 0);
}
