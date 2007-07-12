<?php
header("Content-Type: text/plain; charset=utf-8");

function dump_table($table, $data = true) {
	global $mysql;
	$result = $mysql->query("SHOW CREATE TABLE " . idf_escape($table));
	if ($result) {
		echo $mysql->result($result, 1) . ";\n";
		$result->free();
		if ($data) {
			$result = $mysql->query("SELECT * FROM " . idf_escape($table)); //! enum and set as numbers, binary as _binary
			if ($result) {
				while ($row = $result->fetch_row()) {
					echo "INSERT INTO " . idf_escape($table) . " VALUES ('" . implode("', '", array_map(array($mysql, 'escape_string'), $row)) . "');\n";
				}
				$result->free();
			}
		}
		echo "\n";
	}
}

function dump($db) {
	global $mysql;
	static $routines;
	if (!isset($routines)) {
		$routines = array();
		if ($mysql->server_info >= 5) {
			foreach (array("FUNCTION", "PROCEDURE") as $routine) {
				$result = $mysql->query("SHOW $routine STATUS");
				while ($row = $result->fetch_assoc()) {
					if (!strlen($_GET["db"]) || $row["Db"] === $_GET["db"]) {
						$routines[$row["Db"]][] = $mysql->result($mysql->query("SHOW CREATE $routine " . idf_escape($row["Db"]) . "." . idf_escape($row["Name"])), 2) . ";;\n\n";
					}
				}
				$result->free();
			}
		}
	}
	
	$result = $mysql->query("SHOW CREATE DATABASE " . idf_escape($db));
	if ($result) {
		echo $mysql->result($result, 1) . ";\n";
		$result->free();
	}
	echo "USE " . idf_escape($db) . ";\n";
	echo "SET CHARACTER SET utf8;\n\n";
	$result = $mysql->query("SHOW TABLE STATUS");
	while ($row = $result->fetch_assoc()) {
		dump_table($row["Name"], isset($row["Engine"]));
	}
	$result->free();
	
	if ($mysql->server_info >= 5) {
		$result = $mysql->query("SHOW TRIGGERS");
		if ($result->num_rows || $routines[$db]) {
			echo "DELIMITER ;;\n\n";
		}
		while ($row = $result->fetch_assoc()) {
			echo "CREATE TRIGGER " . idf_escape($row["Trigger"]) . " $row[Timing] $row[Event] ON " . idf_escape($row["Table"]) . " FOR EACH ROW $row[Statement];;\n\n";
		}
		echo implode("", (array) $routines[$db]);
		if ($result->num_rows || $routines[$db]) {
			echo "DELIMITER ;\n\n";
		}
		$result->free();
	}
	
	echo "\n\n";
}

if (!strlen($_GET["db"])) {
	$result = $mysql->query("SHOW DATABASES");
	while ($row = $result->fetch_assoc()) {
		if ($row["Database"] != "information_schema" || $mysql->server_info < 5) {
			if ($mysql->select_db($row["Database"])) {
				dump($row["Database"]);
			}
		}
	}
	$result->free();
} elseif (strlen($_GET["dump"])) {
	dump_table($_GET["dump"]);
} else {
	dump($_GET["db"]);
}
