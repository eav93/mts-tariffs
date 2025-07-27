<?php
/**
 * MTS Tariff Price Parser
 *
 * This script fetches tariff information from MTS regional websites,
 * extracts pricing data, and consolidates it into a single JSON file.
 */

use Illuminate\Http\Client\Factory as Http;

require __DIR__ . '/vendor/autoload.php';

/**
 * Logger class for standardized output
 */
class Logger
{
    const INFO = 'INFO';
    const ERROR = 'ERROR';
    const SUCCESS = 'SUCCESS';

    /**
     * Log a message with a specific level
     *
     * @param string $message The message to log
     * @param string $level The log level
     */
    public static function log(string $message, string $level = self::INFO)
    {
        echo "[" . date('Y-m-d H:i:s') . "] [$level] $message" . PHP_EOL;
    }
}

/**
 * Ensures the specified directory exists
 *
 * @param string $dirPath Path to the directory
 * @return bool True if directory exists or was created successfully
 */
function ensureDirectoryExists(string $dirPath): bool
{
    if (is_dir($dirPath)) {
        return true;
    }

    if (mkdir($dirPath, 0755)) {
        Logger::log("Directory '$dirPath' created successfully", Logger::SUCCESS);
        return true;
    } else {
        Logger::log("Failed to create directory '$dirPath'", Logger::ERROR);
        return false;
    }
}

/**
 * Loads JSON data from a file
 *
 * @param string $filePath Path to the JSON file
 * @return object|null Decoded JSON data or null on error
 */
function loadJsonFromFile(string $filePath): ?object
{
    if (!file_exists($filePath)) {
        return null;
    }

    Logger::log("Loading data from file: $filePath");
    $content = file_get_contents($filePath);
    $data = json_decode($content);

    if (json_last_error() !== JSON_ERROR_NONE) {
        Logger::log("JSON decode error: " . json_last_error_msg(), Logger::ERROR);
        return null;
    }

    return $data;
}

/**
 * Saves data to a JSON file
 *
 * @param string $filePath Path to save the JSON file
 * @param mixed $data Data to save
 * @return bool True if saved successfully
 */
function saveJsonToFile(string $filePath, mixed $data): bool
{
    Logger::log("Saving data to file: $filePath");
    $result = file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));

    if ($result === false) {
        Logger::log("Failed to save file: $filePath", Logger::ERROR);
        return false;
    }

    return true;
}

/**
 * Fetches tariff data for a specific region
 *
 * @param Http $client HTTP client instance
 * @param object $region Region object with alias property
 * @param string $cacheDir Directory to cache JSON files
 * @param bool $refreshCache Whether to refresh the cache
 * @return object|null Tariff data or null on error
 */
function fetchRegionalTariffData(Http $client, object $region, string $cacheDir, bool $refreshCache = false): ?object
{
    $regionAlias = $region->alias;
    $cacheFile = "$cacheDir/$regionAlias.json";

    // Try to load from cache first (unless refresh is requested)
    if (!$refreshCache) {
        $tariffs = loadJsonFromFile($cacheFile);
        if ($tariffs !== null) {
            Logger::log("Using cached data for region: $regionAlias");
            return $tariffs;
        }
    } else {
        Logger::log("Cache refresh requested for region: $regionAlias");
    }

    // Fetch from website if not in cache
    Logger::log("Fetching data from website for region: $regionAlias");
    try {
        $response = $client->get("https://$regionAlias.mts.ru/personal/export/dla-smartfona");
        $html = $response->body();

        // Extract tariff data from JavaScript in the HTML
        if (preg_match('/window\.globalSettings\.tariffs\s*=\s*(.*);\s*<\/script>/', $html, $matches)) {
            $jsonString = trim($matches[1]);
            $tariffs = json_decode($jsonString);

            if (json_last_error() === JSON_ERROR_NONE) {
                // Cache the data
                saveJsonToFile($cacheFile, $tariffs);
                return $tariffs;
            } else {
                Logger::log("Failed to decode JSON for region $regionAlias: " . json_last_error_msg(), Logger::ERROR);
            }
        } else {
            Logger::log("Failed to extract tariff data from HTML for region $regionAlias", Logger::ERROR);
        }
    } catch (\Exception $e) {
        Logger::log("Error fetching data for region $regionAlias: " . $e->getMessage(), Logger::ERROR);
    }

    return null;
}

/**
 * Extracts the price from a tariff object
 *
 * @param object $tariff Tariff object
 * @return float|null Price or null if not found
 */
function extractTariffPrice(object $tariff): ?float
{
    // Case 1: Configurable tariff with packages
    if (isset($tariff->configurableTariffSettings->packages)) {
        return min(array_map(
            fn($package) => $package->subscriptionFee->numValue,
            $tariff->configurableTariffSettings->packages
        ));
    }

    // Case 2: Direct subscription fee
    if (isset($tariff->subscriptionFee)) {
        return $tariff->subscriptionFee->numValue;
    }

    // Case 3: Parametrized tariff settings
    if (isset($tariff->parametrizedTariffSettings)) {
        return $tariff->parametrizedTariffSettings->defaultPackagePrice;
    }

    // No price found
    return null;
}

/**
 * Main function to process all regions and collect tariff prices
 * 
 * Command line options:
 * --refresh-cache: Force refresh of all cached data
 */
$startTime = microtime(true);
Logger::log("Starting MTS tariff price parser");

// Parse command line options
$refreshCache = false;
foreach ($argv ?? [] as $arg) {
    if ($arg === '--refresh-cache') {
        $refreshCache = true;
        Logger::log("Cache refresh mode enabled - all cached data will be refreshed");
    }
}

// Initialize HTTP client
$client = new Http();

// Set up directories
$jsonDir = __DIR__ . '/json';
if (!ensureDirectoryExists($jsonDir)) {
    exit(1);
}

// Fetch list of regions
try {
    Logger::log("Fetching list of regions");
    $regions = $client->get('https://mts.ru/api/bff/v1/regions/list')->object();
    Logger::log("Found " . count($regions) . " regions");
} catch (\Exception $e) {
    Logger::log("Failed to fetch regions list: " . $e->getMessage(), Logger::ERROR);
    exit(1);
}

// Process each region
$regionalPrices = [];
$processedRegions = 0;
$totalRegions = count($regions);

foreach ($regions as $region) {
    $processedRegions++;
    $regionAlias = $region->alias;
    Logger::log("Processing region {$processedRegions}/{$totalRegions}: {$region->title} ($regionAlias)");

    $tariffs = fetchRegionalTariffData($client, $region, $jsonDir, $refreshCache);
    if ($tariffs === null) {
        Logger::log("Skipping region $regionAlias due to data fetch failure", Logger::ERROR);
        continue;
    }

    // Process each tariff
    foreach ($tariffs->actualTariffs as $tariff) {
        // Skip non-mobile tariffs
        if ($tariff->tariffType != 'Mobile') {
            continue;
        }

        $price = extractTariffPrice($tariff);
        if ($price !== null) {
            $regionalPrices[$tariff->alias][$regionAlias] = $price;
        } else {
            Logger::log("No price found for tariff {$tariff->alias} in region $regionAlias", Logger::ERROR);
        }
    }
}

// Save consolidated results
$resultFile = __DIR__ . '/result.json';
if (saveJsonToFile($resultFile, $regionalPrices)) {
    $tariffCount = count($regionalPrices);
    Logger::log("Successfully saved pricing data for $tariffCount tariffs to $resultFile", Logger::SUCCESS);
}

// Find and display the cheapest regions for each tariff
Logger::log("Cheapest regions for each tariff:", Logger::INFO);
foreach ($regionalPrices as $tariffAlias => $regions) {
    // Find the minimum price for this tariff
    $minPrice = min($regions);
    
    // Find all regions with this minimum price
    $cheapestRegions = array_keys($regions, $minPrice);
    
    // Format the list of cheapest regions
    $regionsList = implode(', ', $cheapestRegions);
    
    Logger::log("Tariff '$tariffAlias': {$minPrice} RUB - Cheapest in regions: $regionsList", Logger::SUCCESS);
}

$executionTime = round(microtime(true) - $startTime, 2);
Logger::log("Parser completed in $executionTime seconds", Logger::SUCCESS);
