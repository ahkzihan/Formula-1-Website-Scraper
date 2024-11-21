<?php
require 'vendor/autoload.php';
use GuzzleHttp\Client;

// Function to get all country circuit links from the main F1 2024 page
function getCountryLinks() {
    $client = new Client();
    $countryLinks = [];
    
    try {
        $response = $client->get('https://www.formula1.com/en/racing/2024');
        $htmlString = (string) $response->getBody();

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($htmlString);
        $xpath = new DOMXPath($dom);

        // Scrape country links from the main page
        $nodes = $xpath->query('//a[contains(@class, "outline-scienceBlue")]/@href');
        foreach ($nodes as $node) {
            $link = 'https://www.formula1.com' . $node->nodeValue . '/circuit';
            $countryLinks[] = $link;
        }
    } catch (\Exception $e) {
        // Log or handle error
    }

    return $countryLinks;
}

// Function to scrape individual circuit details
function scrapeCircuitDetails($circuitUrl) {
    $client = new Client();
    
    try {
        $response = $client->get($circuitUrl);
        $htmlString = (string) $response->getBody();

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($htmlString);
        $xpath = new DOMXPath($dom);

        // Scrape the track name
        $trackName = trim($xpath->evaluate('string(//h2[contains(@class, "f1-heading")]/div)'));

        // Scrape the number of laps
        $laps = trim($xpath->evaluate('string(//span[contains(text(), "Number of Laps")]/following-sibling::h2)'));

        // Scrape the fastest lap time
        $fastestLap = trim($xpath->evaluate('string(//span[contains(text(), "Lap Record")]/following-sibling::h2)'));
        $baseLapTime = convertFastestLapToSeconds($fastestLap);

        // Determine track type
        $paragraphs = $xpath->evaluate('//section[@class="w-full"]//p');
        $trackType = 'race';
        foreach ($paragraphs as $paragraph) {
            $paragraphText = strtolower($paragraph->textContent);
            if (strpos($paragraphText, 'street') !== false || strpos($paragraphText, 'streets') !== false) {
                $trackType = 'street';
                break;
            }
        }

        // Check if any required fields are empty
        if (empty($trackName) || empty($laps) || empty($baseLapTime) || empty($trackType)) {
            return null; // Skip this track if any required field is missing
        }

        return [
            'name' => $trackName,
            'type' => $trackType,
            'baseLapTime' => $baseLapTime,
            'laps' => (int)$laps
        ];
    } catch (\Exception $e) {
        // Log or handle the error and return null to skip this track
        return null;
    }
}

// Function to convert the fastest lap string to seconds (assumes format "1:34.486")
function convertFastestLapToSeconds($fastestLap) {
    if (empty($fastestLap)) {
        return null;
    }

    $parts = explode(':', $fastestLap);
    if (count($parts) == 2) {
        $minutes = (int)$parts[0];
        $seconds = (float)$parts[1];
        return ($minutes * 60) + $seconds;
    }

    return null;
}

// Function to scrape all circuits by iterating over each country's circuit link
function scrapeAllCircuits() {
    $circuitLinks = getCountryLinks();
    $allCircuitData = [];

    foreach ($circuitLinks as $index => $link) {
        $circuitData = scrapeCircuitDetails($link);
        if ($circuitData !== null) { // Only add complete data
            $circuitData['id'] = $index + 1; // Assign a unique ID to each circuit
            $allCircuitData[] = $circuitData;
        }
    }

    return $allCircuitData;
}

// Function to output all circuits data in the required format
function outputAllCircuitsData() {
    try {
        $allCircuits = scrapeAllCircuits();
        echo json_encode([
            'code' => 200,
            'result' => $allCircuits
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'code' => 500,
            'result' => 'Error scraping all circuits: ' . $e->getMessage()
        ]);
    }
}

// Run the function to output data
outputAllCircuitsData();
?>
