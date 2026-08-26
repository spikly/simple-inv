<?php

return [
	'db' => [
		'host' => '',
		'username' => '',
		'password' => '',
		'database' => '',
		'charset' => 'utf8mb4',
	],

	'site' => [
		// Shown in the browser tab and the header.
		'title' => 'Inventory Tracker',

		// How many rows a listing shows before it starts a new page.
		'per_page' => 50,
	],

	// Show full error details on screen instead of just logging them.
	// Leave this off unless you are tracking a problem down.
	'debug' => false,
];
