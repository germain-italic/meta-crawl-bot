<?php

include('config.php');


// Check if a command-line argument is provided
if (isset($argv[1])) {
    // Format and use the provided URL
    $startingURL = formatUrl($argv[1]);
} else {
    // Fallback to the URL from config.php
    $startingURL = formatUrl($startingURL);
}

$files = createFiles($startingURL);

// Clear the CSV
$csvFile = fopen($files['csvResults'], 'w');


// Start output buffering
ob_start();


// Initialize data store arrays
$crawledURLs = [];
$internalURLs = [];
$allMetaInfo = [];
$externalURLs = [];
$notFoundURLs = [];


// Start the crawler with the starting URL
crawlURL($startingURL);



// Function to crawl a URL and extract meta and title information
function crawlURL($url) {
    global $startingURL, $crawledURLs, $internalURLs, $excludedPaths,
			$excludedMetaKeys, $allMetaInfo,
			$debugCounter, $debugLimit;


	// Remove trailing slash
	$url = rtrim($url, '/');

	// If the debug counter reaches the limit, return
	if ($debugCounter >= $debugLimit) {
        // echo "Debug limit reached, stopping.\n";
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
    preg_match_all('/href="([^"]+)"/', $pageContent, $matches);
    $localURLs = findUrls($matches[1]);

    // Crawl the extracted internal URLs
    foreach ($localURLs as $internalURL) {
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
$csvFile = fopen($files['csvResults'], 'w');
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
$csvFileNotFound = fopen($files['csv404'], 'w');
foreach ($notFoundURLs as $notFoundUrl) {
    fputcsv($csvFileNotFound, [$notFoundUrl]);
}
fclose($csvFileNotFound);


// After finishing the crawl, save the external URLs to a CSV file
$csvFileExternal = fopen($files['csvExternals'], 'w');
foreach ($externalURLs as $externalUrl) {
    fputcsv($csvFileExternal, [$externalUrl]);
}
fclose($csvFileExternal);




function getContents($url) {
    global $notFoundURLs, $startingURL;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Don't follow redirects automatically
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36');

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode == 301 || $httpCode == 302) {
        $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $startingURL = $redirectUrl; // Update the starting URL
        curl_close($ch);
        return getContents($redirectUrl); // Recursively call the function with the new URL
    }

    if ($httpCode == 404) {
        $notFoundURLs[] = $url; // Log the 404 URL
    }

    // Close the cURL session
    curl_close($ch);

    // Return the content of the URL
    return file_get_contents($url);
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


function findUrls($hrefs) {
    global $crawledURLs, $internalURLs, $externalURLs, $assetExtensions;

    $localURLs = [];

    foreach($hrefs as $href) {
        message("URL: $href");

        $url = normalizeUrl($href);

        // Exclude assets
        if (isAssetFile($url, $assetExtensions)) {
            message("     Excluding asset file");
            continue;
        }


        // Exclude duplicates
        if (in_array($url, $crawledURLs) || in_array($url, $internalURLs)) {
            message("      Excluding duplicate");
            continue;
        }


        // Exclude external URLs
        if (!isInternalUrl($url)) {
            message("     Excluding external URL");
            $externalURLs[] = $url; // Log the external URL
            continue;
        }


        // Exclude anchor links
        if (isAnchorLink($url)) {
            message("     Excluding anchor link");
            continue;
        }


        // Exclude blacklisted path
        if (isInBlacklistedPath($url)) {
            message("     Excluding blacklisted path");
            continue;
        }


        // Add valid URL
        $localURLs[] = $url;
        $internalURLs[] = $url;


    }

    return $localURLs;
}



function isAssetFile($url, $assetExtensions) {
    foreach ($assetExtensions as $extension) {
        if (preg_match('/' . preg_quote($extension) . '$/', $url)) {
            return true;
        }
    }
    return false;
}



function normalizeUrl($href) {
    global $startingURL;

    // Check if the URL is already absolute
    if (strpos($href, 'http') === 0) {
        return $href;
    }

    // Extract the scheme and host from the starting URL
    $scheme = parse_url($startingURL, PHP_URL_SCHEME);
    $host = parse_url($startingURL, PHP_URL_HOST);

    // Construct the base URL
    $baseUrl = $scheme . '://' . $host;

    // Normalize the URL
    return rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
}




function isInBlacklistedPath($url) {
    global $excludedPaths;

    foreach($excludedPaths as $exPath) {
        if (strstr($url, $exPath)) {
            return true;
        }
    }
}




function isAnchorLink($url) {
    // Parse the URL and return true if there's a fragment (portion after the #)
    $parsedUrl = parse_url($url);
    return isset($parsedUrl['fragment']) && !empty($parsedUrl['fragment']);
}



function isInternalUrl($url) {
    global $startingURL;

    // Extract the host from the starting URL
    $startingDomain = parse_url($startingURL, PHP_URL_HOST);

    // Normalize the URL if it's a relative URL
    if (strpos($url, 'http') !== 0) {
        $url = rtrim($startingURL, '/') . '/' . ltrim($url, '/');
    }

    // Extract the host from the input URL
    $urlDomain = parse_url($url, PHP_URL_HOST);

    // Compare domain names, ignoring HTTP and HTTPS
    return strtolower($startingDomain) === strtolower($urlDomain);
}



function sanitizeUrlForFolderName($url) {
    // Remove the protocol (http, https)
    $sanitized = preg_replace('#^https?://#', '', $url);

    // Remove 'www.'
    $sanitized = str_replace('www.', '', $sanitized);

    // Replace any characters that are not letters, numbers, or hyphens with an underscore
    $sanitized = preg_replace('/[^a-zA-Z0-9\-]/', '_', $sanitized);

    return $sanitized;
}



function createFiles($startingURL) {

    $files = [];

    // Create a new directory inside 'results' with the current date and time
    $dateFolder = 'results/' . sanitizeUrlForFolderName($startingURL) . '-' . date('Y_m_d-H_i_s');
    if (!file_exists($dateFolder)) {
        mkdir($dateFolder, 0777, true);
    }

    // Define file paths with the new directory
    $files['csvResults']  = $dateFolder . '/crawled_data.csv';
    $files['csv404']          = $dateFolder . '/404_urls.csv';
    $files['csvExternals']    = $dateFolder . '/external_urls.csv';

    return $files;
}



// Function to format a domain name into a well-formed URL
function formatUrl($input) {
    if (!preg_match('~^https?://~', $input)) {
        return 'http://' . $input;
    }
    return $input;
}