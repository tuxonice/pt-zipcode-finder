<?php

namespace Tlab\PtZipcodeFinder\Importer;

use PDO;
use RuntimeException;
use Tlab\PtZipcodeFinder\Database\DatabaseManager;

/**
 * ZipcodeImporter
 *
 * A standalone class for importing Portuguese zipcode data from CSV files
 * into an SQLite database without Symfony Console dependencies.
 */
class ZipcodeImporter
{
    private const DISTRICTS_FILE_NAME = 'districts.csv';

    private const MUNICIPALITIES_FILE_NAME = 'municipalities.csv';

    private const ZIPCODES_FILE_NAME = 'zipcodes.csv';

    private DatabaseManager $databaseManager;

    private PDO $pdo;

    /**
     * @var callable|null
     */
    private $logger = null;

    private int $importedDistricts = 0;

    private int $importedMunicipalities = 0;

    private int $importedZipcodes = 0;

    private int $failedImportRows = 0;

    /**
     * Set a logger callback function to receive status updates
     *
     * @param callable $logger Function that accepts (string $message, string $type)
     * @return self
     */
    public function setLogger(callable $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Import all data from the specified directory
     *
     * @param string $sourceFolder Path to directory containing CSV files
     * @return bool True if import was successful, false otherwise
     * @throws RuntimeException If directory doesn't exist
     */
    public function import(string $sourceFolder, string $databaseFilePath): bool
    {
        $this->databaseManager = new DatabaseManager($databaseFilePath);
        $this->pdo = $this->databaseManager->getPdo();

        // Reset counters
        $this->importedDistricts = 0;
        $this->importedMunicipalities = 0;
        $this->importedZipcodes = 0;
        $this->failedImportRows = 0;

        // Validate directory
        if (!is_dir($sourceFolder)) {
            throw new RuntimeException("Directory '$sourceFolder' does not exist.");
        }

        // Create tables
        $this->log('Creating database tables', 'info');
        $this->databaseManager->createTables();
        $this->log('Database tables created successfully', 'success');

        // Import data
        try {
            $this->importDistricts($sourceFolder);
            $this->importMunicipalities($sourceFolder);
            $this->importZipcodes($sourceFolder);
            $this->log('All data imported successfully', 'success');
            return true;
        } catch (\Exception $e) {
            $this->log('Error importing data: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Get the number of imported districts
     *
     * @return int
     */
    public function getImportedDistrictsCount(): int
    {
        return $this->importedDistricts;
    }

    /**
     * Get the number of imported municipalities
     *
     * @return int
     */
    public function getImportedMunicipalitiesCount(): int
    {
        return $this->importedMunicipalities;
    }

    /**
     * Get the number of imported zipcodes
     *
     * @return int
     */
    public function getImportedZipcodesCount(): int
    {
        return $this->importedZipcodes;
    }

    public function getFailedImportRows(): int
    {
        return $this->failedImportRows;
    }

    /**
     * Import districts from CSV file
     *
     * @param string $directory Directory containing the CSV files
     * @throws RuntimeException If file doesn't exist or can't be read
     */
    private function importDistricts(string $directory): void
    {
        $filePath = $directory . DIRECTORY_SEPARATOR . self::DISTRICTS_FILE_NAME;
        if (!file_exists($filePath)) {
            throw new RuntimeException("Districts file not found: $filePath");
        }

        $this->log('Importing districts', 'info');
        $stmt = $this->pdo->prepare('INSERT INTO districts (id, name) VALUES (?, ?)');

        $count = 0;
        $file = fopen($filePath, 'r');
        if (!$file) {
            throw new RuntimeException('Error opening districts file');
        }

        while (($line = fgets($file)) !== false) {
            // Detect encoding and convert to UTF-8 if needed
            $encoding = mb_detect_encoding($line, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
            if ($encoding === false) {
                $this->log('Unable to detect encoding. Skipping line: ' . $line, 'warning');
                continue;
            }
            if ($encoding !== 'UTF-8') {
                $line = mb_convert_encoding($line, 'UTF-8', $encoding);
            }

            $data = str_getcsv(trim((string)$line), ';');
            if (count($data) !== 2) {
                $this->log("Invalid district data: " . implode(';', $data), 'warning');
                $this->failedImportRows++;
                continue;
            }

            $stmt->execute([$data[0], $data[1]]);
            $count++;
        }
        fclose($file);

        $this->importedDistricts = $count;
        $this->log("Imported $count districts", 'success');
    }

    /**
     * Import municipalities from CSV file
     *
     * @param string $directory Directory containing the CSV files
     * @throws RuntimeException If file doesn't exist or can't be read
     */
    private function importMunicipalities(string $directory): void
    {
        $filePath = $directory . DIRECTORY_SEPARATOR . self::MUNICIPALITIES_FILE_NAME;
        if (!file_exists($filePath)) {
            throw new RuntimeException("Municipalities file not found: $filePath");
        }

        $this->log('Importing municipalities', 'info');
        $stmt = $this->pdo->prepare('INSERT INTO municipalities (district_id, id, name) VALUES (?, ?, ?)');

        $count = 0;
        $file = fopen($filePath, 'r');
        if (!$file) {
            throw new RuntimeException('Error opening municipalities file');
        }

        while (($line = fgets($file)) !== false) {
            // Detect encoding and convert to UTF-8 if needed
            $encoding = mb_detect_encoding($line, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
            if ($encoding === false) {
                $this->log('Unable to detect encoding. Skipping line: ' . $line, 'warning');
                $this->failedImportRows++;
                continue;
            }
            if ($encoding !== 'UTF-8') {
                $line = mb_convert_encoding($line, 'UTF-8', $encoding);
            }

            $data = str_getcsv(trim((string)$line), ';');
            if (count($data) !== 3) {
                $this->log("Invalid municipality data: " . implode(';', $data), 'warning');
                $this->failedImportRows++;
                continue;
            }

            $stmt->execute([$data[0], $data[1], $data[2]]);
            $count++;
        }
        fclose($file);

        $this->importedMunicipalities = $count;
        $this->log("Imported $count municipalities", 'success');
    }

    /**
     * Import zipcodes from CSV file
     *
     * @param string $directory Directory containing the CSV files
     * @throws RuntimeException If file doesn't exist or can't be read
     */
    private function importZipcodes(string $directory): void
    {
        $filePath = $directory . DIRECTORY_SEPARATOR . self::ZIPCODES_FILE_NAME;
        if (!file_exists($filePath)) {
            throw new RuntimeException("Zipcodes file not found: $filePath");
        }

        $this->log('Importing zipcodes', 'info');
        $stmt = $this->pdo->prepare('
            INSERT INTO zipcodes (
                district_id, municipality_id, locality_id, locality_name,
                street_code, street_type, first_prep, street_title,
                second_prep, street_name, street_location, section,
                door, client, zipcode, extension, postal_designation
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $count = 0;
        $batchSize = 1000;
        $inTransaction = false;
        $file = fopen($filePath, 'r');
        if (!$file) {
            throw new RuntimeException('Error opening zipcodes file');
        }

        try {
            $this->pdo->beginTransaction();
            $inTransaction = true;

            while (($line = fgets($file)) !== false) {
                // Detect encoding and convert to UTF-8 if needed
                $encoding = mb_detect_encoding($line, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
                if ($encoding === false) {
                    $this->log('Unable to detect encoding. Skipping line: ' . $line, 'warning');
                    $this->failedImportRows++;
                    continue;
                }
                if ($encoding !== 'UTF-8') {
                    $line = mb_convert_encoding($line, 'UTF-8', $encoding);
                }

                $data = str_getcsv(trim((string)$line), ';');
                if (count($data) !== 17) {
                    $this->log("Invalid zipcode data: " . implode(';', $data), 'warning');
                    $this->failedImportRows++;
                    continue; // Skip invalid lines
                }

                // Empty strings to null for optional fields
                for ($i = 4; $i <= 13; $i++) {
                    if ($data[$i] === '') {
                        $data[$i] = null;
                    }
                }

                $stmt->execute($data);
                $count++;

                if ($count % $batchSize === 0) {
                    $this->pdo->commit();
                    $this->pdo->beginTransaction();
                    $this->log("Imported $count zipcodes so far", 'info');
                }
            }

            $this->pdo->commit();
            $inTransaction = false;
        } catch (\Throwable $e) {
            if ($inTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            fclose($file);
            throw new RuntimeException('Error importing zipcodes: ' . $e->getMessage());
        }

        fclose($file);

        $this->importedZipcodes = $count;
        $this->log("Imported $count zipcodes", 'success');
    }

    /**
     * Log a message if a logger is set
     *
     * @param string $message The message to log
     * @param string $type The type of message (info, warning, error, success)
     */
    private function log(string $message, string $type = 'info'): void
    {
        if ($this->logger) {
            call_user_func($this->logger, $message, $type);
        }
    }
}
