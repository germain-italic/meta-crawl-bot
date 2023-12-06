<?php

include('config.php');

// Initialize a counter for debugging
$debugCounter = 0;


// Clear the CSV
$csvFile = fopen($csvFileName, 'w');


// Start output buffering
ob_start();


// Initialize an empty array to store the crawled URLs
$crawledURLs = [];


// Initialize an array to store all meta information
$allMetaInfo = [];



// Function to crawl a URL and extract meta and title information
function crawlURL($url) {
    global $startingURL, $crawledURLs, $excludedPaths,
			$excludedMetaKeys, $allMetaInfo,
			$debugCounter, $debugLimit;


	// Remove trailing slash
	$url = rtrim($url, '/');

	// If the debug counter reaches the limit, return
	if ($debugCounter >= $debugLimit) {
        // echo "Debug limit reached, stopping.\n";
        return;
    }


	// Excluse external URLs
	if (!strstr($url, $startingURL)) {
		return;
	}


    // Check if the URL has already been crawled
    if (in_array($url, $crawledURLs)) {
        return;
    }


	// Increment the debug counter
    $debugCounter++;


    // Add the URL to the crawled URLs array
    $crawledURLs[] = $url;

	echo "Crawling URL: " . $url . "\n";
    ob_flush();
    flush();

	$options = array(
	'http' => array(
		'method' => "GET",
		'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36\r\n"
	)
	);

	$context = stream_context_create($options);


    // Get the page content
    $pageContent = file_get_contents($url, false, $context);

    // Extract meta tags
    $metaTags = [];
	preg_match_all('/<meta (?:name|property)="([^"]+)" content="([^"]+)"/i', $pageContent, $matches);
	for ($i = 0; $i < count($matches[1]); $i++) {
		$metaTags[$matches[1][$i]] = $matches[2][$i];
	}


	// Filter out excluded meta tags
    foreach ($excludedMetaKeys as $excludedKey) {
        if (isset($metaTags[$excludedKey])) {
            unset($metaTags[$excludedKey]);
        }
    }


    // Extract title tag
    $titleTag = '';
    preg_match('/<title>(.*?)<\/title>/i', $pageContent, $matches);
    if (isset($matches[1])) {
        $titleTag = $matches[1];
    }


    // Add meta information for the current URL to the global array
    $allMetaInfo[$url] = [
        'url' => $url,
        'meta' => $metaTags,
        'title' => $titleTag
    ];


    // Extract internal URLs from the page content
	$internalURLs = [];
	preg_match_all('/href="([^"]+)"/', $pageContent, $matches);
	foreach ($matches[1] as $href) {
		$isExcluded = false;

		// Normalize the URL
		$parsedUrl = parse_url($href);
		$queryParams = [];
		parse_str($parsedUrl['query'] ?? '', $queryParams);


		// Normalize the href
		$fullUrl = strpos($href, 'http') === 0 ? $href : rtrim($url, '/') . '/' . ltrim($href, '/');
		$relUrl  = str_replace($startingURL, '', $fullUrl,);

		// echo "  relUrl: " . $relUrl. "\n";
		// ob_flus();
		// flush();

		// Check against each excluded path
		foreach ($excludedPaths as $exPath) {

			// echo "    is $exPath in $relUrl ?\n";
			// ob_flush();
			// flush();

			if (strstr($relUrl, $exPath)) {
				$isExcluded = true;

				// echo "        => Exclude $exPath\n";
				// ob_flush();
				// flush();

				break;
			}
		}

		// If not excluded, add to internal URLs
		if (!$isExcluded) {
			$internalURLs[] = $fullUrl;
		}
	}


    // Crawl the extracted internal URLs
    foreach ($internalURLs as $internalURL) {
        crawlURL($internalURL);
    }
}

// Crawl the starting URL
crawlURL($startingURL);


// After crawling, create headers based on collected meta information
$headers = ['url'];
$metaKeys = [];
foreach ($allMetaInfo as $info) {
    $metaKeys = array_merge($metaKeys, array_keys($info['meta']));
}
$metaKeys = array_unique($metaKeys);
$headers = array_merge($headers, array_map(function($k) { return 'meta_' . $k; }, $metaKeys));
$headers[] = 'title';

// Write headers and data to CSV
$csvFile = fopen($csvFileName, 'w');
fputcsv($csvFile, $headers);
foreach ($allMetaInfo as $info) {
    $row = ['url' => $info['url']];
    foreach ($metaKeys as $key) {
        $row['meta_' . $key] = $info['meta'][$key] ?? '';
    }
    $row['title'] = $info['title'];
    fputcsv($csvFile, $row);
}
fclose($csvFile);
