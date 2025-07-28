<?php

namespace Tlab\PtZipcodeFinder\Database;

use PDO;
use PDOException;
use RuntimeException;

class DatabaseManager
{
    private PDO $pdo;

    public function __construct(readonly private string $databaseFilePath)
    {
        $this->ensureDatabaseFileExists();
        $this->connect();
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function getDatabaseFilePath(): string
    {
        return $this->databaseFilePath;
    }

    public function createTables(): void
    {
        $this->createDistrictsTable();
        $this->createMunicipalitiesTable();
        $this->createZipcodesTable();
    }

    private function createDistrictsTable(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS districts (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL
            )
        ');
    }

    private function createMunicipalitiesTable(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS municipalities (
                id TEXT NOT NULL,
                district_id TEXT NOT NULL,
                name TEXT NOT NULL,
                PRIMARY KEY (id, district_id),
                FOREIGN KEY (district_id) REFERENCES districts(id)
            )
        ');
    }

    private function createZipcodesTable(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS zipcodes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                district_id TEXT NOT NULL,
                municipality_id TEXT NOT NULL,
                locality_id TEXT NOT NULL,
                locality_name TEXT NOT NULL,
                street_code TEXT,
                street_type TEXT,
                first_prep TEXT,
                street_title TEXT,
                second_prep TEXT,
                street_name TEXT,
                street_location TEXT,
                section TEXT,
                door TEXT,
                client TEXT,
                zipcode TEXT NOT NULL,
                extension TEXT NOT NULL,
                postal_designation TEXT NOT NULL,
                FOREIGN KEY (district_id) REFERENCES districts(id),
                FOREIGN KEY (municipality_id, district_id) REFERENCES municipalities(id, district_id)
            )
        ');

        // Create indexes for better search performance
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_zipcode ON zipcodes(zipcode)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_locality ON zipcodes(locality_name)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_street ON zipcodes(street_name)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_zip_full ON zipcodes(zipcode, extension)');
    }

    private function connect(): void
    {
        try {
            $this->pdo = new PDO('sqlite:' . $this->databaseFilePath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        } catch (PDOException $e) {
            throw new RuntimeException('Failed to connect to database: ' . $e->getMessage());
        }
    }

    private function ensureDatabaseFileExists(): void
    {
        if (!file_exists($this->databaseFilePath) || !is_writable($this->databaseFilePath)) {
            throw new RuntimeException("Database file '{$this->databaseFilePath}' does not exist");
        }
    }
}
