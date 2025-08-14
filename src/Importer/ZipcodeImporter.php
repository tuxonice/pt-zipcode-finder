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
    private DatabaseManager $databaseManager;
    private PDO $pdo;
    /**
     * @var callable|null
     */
    private $logger = null;
    private int $importedDistricts = 0;
    private int $importedMunicipalities = 0;
    private int $importedZipcodes = 0;

    /**
     * Constructor
     *
     * @param string $databasePath Path to the database directory
     */
    public function __construct(string $databasePath)
    {
        $this->databaseManager = new DatabaseManager($databasePath);
        $this->pdo = $this->databaseManager->getPdo();
    }

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

    /**
     * Import all data from the specified directory
     *
     * @param string $directory Path to directory containing CSV files
     * @return bool True if import was successful, false otherwise
     * @throws RuntimeException If directory doesn't exist
     */
    public function import(string $directory): bool
    {
        // Reset counters
        $this->importedDistricts = 0;
        $this->importedMunicipalities = 0;
        $this->importedZipcodes = 0;

        // Validate directory
        if (!is_dir($directory)) {
            throw new RuntimeException("Directory '$directory' does not exist.");
        }

        // Create tables
        $this->log('Creating database tables', 'info');
        $this->databaseManager->createTables();
        $this->log('Database tables created successfully', 'success');
        
        // Import data
        try {
            $this->importDistricts($directory);
            $this->importMunicipalities($directory);
            $this->importZipcodes($directory);
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

    /**
     * Import districts from CSV file
     *
     * @param string $directory Directory containing the CSV files
     * @throws RuntimeException If file doesn't exist or can't be read
     */
    private function importDistricts(string $directory): void
    {
        $filePath = $directory . '/distritos.txt';
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

            $data = str_getcsv(trim($line), ';');
            if (count($data) !== 2) {
                $this->log("Invalid district data: " . implode(';', $data), 'warning');
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
        $filePath = $directory . '/concelhos.txt';
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
            if ($encoding !== 'UTF-8') {
                $line = mb_convert_encoding($line, 'UTF-8', $encoding);
            }

            $data = str_getcsv(trim($line), ';');
            if (count($data) !== 3) {
                $this->log("Invalid municipality data: " . implode(';', $data), 'warning');
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
        $filePath = $directory . '/todos_cp.txt';
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
        $this->pdo->beginTransaction();

        $file = fopen($filePath, 'r');
        if (!$file) {
            throw new RuntimeException('Error opening zipcodes file');
        }

        while (($line = fgets($file)) !== false) {
            // Detect encoding and convert to UTF-8 if needed
            $encoding = mb_detect_encoding($line, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
            if ($encoding !== 'UTF-8') {
                $line = mb_convert_encoding($line, 'UTF-8', $encoding);
            }

            $data = str_getcsv(trim($line), ';');
            if (count($data) !== 17) {
                $this->log("Invalid zipcode data: " . implode(';', $data), 'warning');
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
        fclose($file);

        $this->importedZipcodes = $count;
        $this->log("Imported $count zipcodes", 'success');
    }

    /**
     * Truncate all tables in reverse order to respect foreign key constraints
     */
    private function truncateTables(): void
    {
        // Disable foreign key checks if your database supports it
        $this->pdo->exec('PRAGMA foreign_keys = OFF');

        // Truncate in reverse order of dependencies
        $this->pdo->exec('DELETE FROM zipcodes');
        $this->pdo->exec('DELETE FROM municipalities');
        $this->pdo->exec('DELETE FROM districts');

        // Reset auto-increment counters if your database uses them
        $this->pdo->exec('DELETE FROM sqlite_sequence WHERE name IN ("zipcodes", "municipalities", "districts")');

        // Re-enable foreign key checks
        $this->pdo->exec('PRAGMA foreign_keys = ON');
    }
}
