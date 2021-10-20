<?php
use League\Csv\Reader;
use \FaaPz\PDO\Database;

require __DIR__ . '/vendor/autoload.php';


$csv = Reader::createFromPath('data_raw.csv', 'r');
$csv->setDelimiter(';');
$csv->setHeaderOffset(0);
$header_offset = $csv->getHeaderOffset(); //returns 0
$header = $csv->getHeader();
$records = $csv->getRecords();

$pdo = new Database('sqlite:db.sqlite', '','');
$sql = "CREATE TABLE `users` (
	`id`	INTEGER PRIMARY KEY AUTOINCREMENT,
	`username`	TEXT NOT NULL UNIQUE,
	`nama`	TEXT NOT NULL,
	`password`	TEXT NOT NULL,
	`kelas`	TEXT NOT NULL
);";
$pdo->query($sql);

foreach ($records as $offset => $record) {
	$insertStatement = $pdo->insert(array(
	   "id" => $offset,
       "nama" =>$record['nama'],
       "username" => $record['username'],
       "password" => password_hash($record['password'],PASSWORD_BCRYPT),
       'kelas' => $record['kelas']
	))
	->into("users");

	$insertId = $insertStatement->execute();
}
