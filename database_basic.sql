## Tabel Users
CREATE TABLE `users` (
	`id`	INTEGER PRIMARY KEY AUTOINCREMENT,
	`username`	TEXT NOT NULL UNIQUE,
	`nama`	TEXT NOT NULL,
	`password`	TEXT NOT NULL,
	`kelas`	TEXT NOT NULL
);