<?php

/**
 * Rename this file config.php and replace with your values
 */

// Set the starting URL (without trailing slash)
$startingURL = '';

// Define the path exclusion list
$excludedPaths = [
	'javascript:',
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
$debugLimit = 999;

// Initialize a counter for debugging
$debugCounter = 0;


// Create a new directory inside 'results' with the current date and time
$dateFolder = 'results/' . sanitizeUrlForFolderName($startingURL) . '-' . date('Y_m_d-H_i_s');
if (!file_exists($dateFolder)) {
    mkdir($dateFolder, 0777, true);
}

// Define file paths with the new directory
$files['csvResults']  = $dateFolder . '/crawled_data.csv';
$files['csv404']          = $dateFolder . '/404_urls.csv';
$files['csvExternals']    = $dateFolder . '/external_urls.csv';