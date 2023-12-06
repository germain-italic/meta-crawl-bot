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


// Initialize an array to store 404s
$notFoundURLs = [];


// Crawl the starting URL
crawlURL($startingURL);



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

    message("Crawling URL: ".$url);

    // Get the page content
	$pageContent = getContents($url);

    // Extract meta tags
    $metaTags = extractMetaTags($pageContent);

    // Extract title tag
    $titleTag = extractTitleTag($pageContent);


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

        // Normalize the href to an absolute URL
        $fullUrl = strpos($href, 'http') === 0 ? $href : rtrim($startingURL, '/') . '/' . ltrim($href, '/');

        // Check against each excluded path
        foreach ($excludedPaths as $exPath) {
            if (strstr($fullUrl, $exPath)) {
                $isExcluded = true;
                message("        => Exclude $exPath");
                break;
            }
        }

        // If not excluded and not already crawled, add to internal URLs
        if (!$isExcluded && !in_array($fullUrl, $crawledURLs)) {
            message("=> Adding $fullUrl to the list");
            $internalURLs[] = $fullUrl;
        }
    }

    // Crawl the extracted internal URLs
    foreach ($internalURLs as $internalURL) {
        crawlURL($internalURL);
    }
}




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



// After finishing the crawl, save the 404 URLs to a CSV file
$csvFileNotFound = fopen($csvFileNotFoundName, 'w');
foreach ($notFoundURLs as $notFoundUrl) {
    fputcsv($csvFileNotFound, [$notFoundUrl]);
}
fclose($csvFileNotFound);



function getContents($url) {
    global $notFoundURLs;

    $options = array(
        'http' => array(
            'method' => "GET",
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36\r\n"
        )
    );

    $context = stream_context_create($options);

    $content = @file_get_contents($url, false, $context);

    if (isset($http_response_header)) {
        $responseCode = explode(' ', $http_response_header[0])[1];
        if ($responseCode == '404') {
            $notFoundURLs[] = $url; // Log the 404 URL
        }
    }

    return $content;
}



function extractMetaTags($pageContent) {
    global $excludedMetaKeys;

    $metaTags = [];
    preg_match_all('/<meta (?:name|property)="([^"]+)" content="([^"]+)"/i', $pageContent, $matches);
    for( $i = 0; $i < count($matches[1]); $i++ ) {
        $metaTags[$matches[1][$i]] = $matches[2][$i];
    }

    // Filter out excluded meta tags
    foreach ($excludedMetaKeys as $excludedKey) {
        if (isset($metaTags[$excludedKey])) {
            unset($metaTags[$excludedKey]);
        }
    }

    return $metaTags;
}



function extractTitleTag($pageContent) {
    $titleTag = '';
    preg_match('/<title>(.*?)<\/title>/i', $pageContent, $matches);
    if (isset($matches[1])) {
        $titleTag = $matches[1];
    }
    return $titleTag;
}



function message($msg) {
    echo $msg . "\n";
    ob_flush();
    flush();
}