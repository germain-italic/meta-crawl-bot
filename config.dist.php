<?php

// Set the starting URL (without trailing slash)
$startingURL = 'https://en.wikipedia.org/wiki/Web_crawler';

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


// Maximum depth to crawl into
$maxDepth = 5;

// Exclude URLs with ?xxx=yyy
$excludeQueryStringURLs = true;


// Define meta keys to exclude
$excludedMetaKeys = ['viewport', 'twitter:card', 'generator'];

// Define file extensions to exclude
$assetExtensions = ['.css', '.js', '.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp', '.ico', '.bmp', '.tiff', '.woff', '.woff2', '.eot', '.ttf', '.otf', '.mp4', '.webm', '.mp3', '.wav', '.pdf', '.xml', '.json'];



// Stop after n calls
$debugLimit = 999;

// Initialize a counter for debugging
$debugCounter = 0;

// Log messages
$showDebug = false;
$showProgress = true;