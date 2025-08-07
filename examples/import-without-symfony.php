<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Tlab\PtZipcodeFinder\Importer\ZipcodeImporter;

// Simple logger function
function logger($message, $type = 'info') {
    $date = date('Y-m-d H:i:s');
    $typeFormatted = str_pad(strtoupper($type), 7, ' ', STR_PAD_RIGHT);
    echo "[$date] [$typeFormatted] $message" . PHP_EOL;
}

// Parse command line arguments
$options = getopt('d:t', ['directory:', 'database:', 'truncate']);

// Get directory from command line or use default
$directory = $options['directory'] ?? $options['d'] ?? null;
if (!$directory) {
    logger('Directory parameter is required (--directory=path or -d path)', 'error');
    exit(1);
}

// Get database path from command line or use default
$databasePath = $options['database'] ?? null;
if (!$databasePath) {
    logger('Database path parameter is required (--database=path)', 'error');
    exit(1);
}

// Check if truncate option is set
$truncate = isset($options['truncate']) || isset($options['t']);

// Validate directory
if (!is_dir($directory)) {
    logger("Directory '$directory' does not exist", 'error');
    exit(1);
}

// Validate database path
if (!is_dir($databasePath)) {
    logger("Database path '$databasePath' does not exist", 'error');
    exit(1);
}

// Create the importer
logger("Starting import from '$directory' to database at '$databasePath'", 'info');
$importer = new ZipcodeImporter($databasePath);
$importer->setLogger('logger');

// Run the import
try {
    $startTime = microtime(true);
    $result = $importer->import($directory, $truncate);
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    
    if ($result) {
        logger(sprintf(
            'Import completed in %s seconds: %d districts, %d municipalities, %d zipcodes',
            $duration,
            $importer->getImportedDistrictsCount(),
            $importer->getImportedMunicipalitiesCount(),
            $importer->getImportedZipcodesCount()
        ), 'success');
        exit(0);
    } else {
        logger('Import failed', 'error');
        exit(1);
    }
} catch (Exception $e) {
    logger('Import failed: ' . $e->getMessage(), 'error');
    exit(1);
}
