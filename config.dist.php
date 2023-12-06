<?php

// Set the starting URL (without trailing slash)
$startingURL = 'https://www.wikipedia.org';

// Define the path exclusion list
$excludedPaths = [
	'/#',
    'javascript:',
	'/legal',
	'/cookies',
	'/xmlrpc.php',
	'/wp-json',
	'/wp-admin',
	'/wp-content/themes',
	'/feed',
	'/comments/feed',
];


// Define meta keys to exclude
$excludedMetaKeys = ['viewport', 'twitter:card', 'generator'];


// Stop after n calls
$debugLimit = 999;

// Initialize a counter for debugging
$debugCounter = 0;

// Common web assets extensions
$assetExtensions = ['.css', '.js', '.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp', '.ico', '.bmp', '.tiff', '.woff', '.woff2', '.eot', '.ttf', '.otf', '.mp4', '.webm', '.mp3', '.wav', '.pdf', '.xml', '.json'];