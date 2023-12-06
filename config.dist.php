<?php

/**
 * Rename this file config.php and replace with your values
 */

// Set the starting URL (without trailing slash)
$startingURL = '';

// Define the path exclusion list
$excludedPaths = [
	'/legal',
	'/cookies',
	'/wp-content/themes',
	'/wp-json',
	'/xmlrpc.php',
	'/#',
	'/wp-admin',
];


// Define meta keys to exclude
$excludedMetaKeys = ['viewport', 'twitter:card', 'generator'];


// Stop after n calls
$debugLimit = 5;


// Name of the CSV file to create
$csvFileName = 'crawled_data.csv';