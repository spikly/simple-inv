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

		// Rows per listing page.
		'per_page' => 50,
	],

	// Show error details on screen, not just in the log. Leave off in normal use.
	'debug' => false,
];
