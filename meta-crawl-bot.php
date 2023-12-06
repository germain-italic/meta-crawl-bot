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





// After crawling, create the destination directory
$files = setDestFiles($startingURL);
createDestFolder($files['csvResults']);


// Clear the CSV
$csvFile = fopen($files['csvResults'], 'w');


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
        $row['meta_' . $key] = html_entity_decode($info['meta'][$key]) ?? '';
    }
    $row['title'] = $info['title'];
    fputcsv($csvFile, $row);
}
message("Results saved to ".$files['csvResults']);
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




function getContents($url) {
    global $notFoundURLs, $startingURL;

    if (!$url) {
        message("URL is empty or not set.");
        return false;
    }

    $curlOptions = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true, // Retrieve both headers and content
        CURLOPT_SSL_VERIFYPEER => false, // Disable SSL verification
        CURLOPT_SSL_VERIFYHOST => false, // Disable SSL host verification
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.5359.108 Safari/537.36', // Set User-Agent to mimic a browser
    ];

    $curlHandle = curl_init();
    curl_setopt_array($curlHandle, $curlOptions);

    $response = curl_exec($curlHandle);

    if (curl_error($curlHandle)) {
        message('cURL error: ' . curl_error($curlHandle));
    } else {
        // Process the response, including headers and content
        $headers = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        $content = curl_getinfo($curlHandle, CURLINFO_CONTENT_TYPE);

        // message("URL: $url");
        // message("HTTP Code: $headers");
        // message("Content Type: $content");
        // message($response);

        if ($headers == 301 || $headers == 302) {
            $redirectUrl = curl_getinfo($curlHandle, CURLINFO_REDIRECT_URL);
            $startingURL = $redirectUrl; // Update the starting URL
            curl_close($curlHandle);
            return getContents($redirectUrl); // Recursively call the function with the new URL
        }

        if ($headers == 404) {
            $notFoundURLs[] = $url; // Log the 404 URL
        }

        // Close the cURL session
        curl_close($curlHandle);

        // Return the content of the URL
        return $response;
    }

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



function setDestFiles($startingURL) {

    $files = [];

    // Create a new directory inside 'results' with the current date and time
    $dateFolder = 'results/' . sanitizeUrlForFolderName($startingURL) . '-' . date('Y_m_d-H_i_s');

    // Define file paths with the new directory
    $files['csvResults']      = $dateFolder . '/crawled_data.csv';
    $files['csv404']          = $dateFolder . '/404_urls.csv';
    $files['csvExternals']    = $dateFolder . '/external_urls.csv';

    return $files;
}



function createDestFolder($fileName) {

    if (!file_exists(dirname($fileName))) {
        mkdir(dirname($fileName), 0777, true);
    }
}



// Function to format a domain name into a well-formed URL
function formatUrl($input) {
    if (!preg_match('~^https?://~', $input)) {
        return 'http://' . $input;
    }
    return $input;
}