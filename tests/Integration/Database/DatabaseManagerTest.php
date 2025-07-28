<?php

namespace Tests\Integration\Database;

use Exception;
use PHPUnit\Framework\TestCase;
use Tlab\PtZipcodeFinder\Database\DatabaseManager;

class DatabaseManagerTest extends TestCase
{
    private string $databasePath;
    private DatabaseManager $databaseManager;

    protected function setUp(): void
    {
        // Create a temporary database file for testing
        $this->databasePath = sys_get_temp_dir() . '/zipcode_test_' . uniqid() . '.sqlite';
        touch($this->databasePath);
        $this->databaseManager = new DatabaseManager($this->databasePath);
    }

    protected function tearDown(): void
    {
        // Clean up the test database file
        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    public function testGetDatabasePath(): void
    {
        $this->assertEquals($this->databasePath, $this->databaseManager->getDatabaseFilePath());
    }

    public function testGetPdo(): void
    {
        $pdo = $this->databaseManager->getPdo();
        $this->assertInstanceOf(\PDO::class, $pdo);

        // Test that PDO is configured correctly
        $attributes = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ];

        foreach ($attributes as $attribute => $expectedValue) {
            $this->assertEquals($expectedValue, $pdo->getAttribute($attribute));
        }
    }

    public function testCreateTables(): void
    {
        // Create the tables
        $this->databaseManager->createTables();

        // Get the PDO connection
        $pdo = $this->databaseManager->getPdo();

        // Check if tables exist
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
        if ($stmt === false) {
            throw new Exception('Unable to query tables');
        }
        $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertContains('districts', $tables);
        $this->assertContains('municipalities', $tables);
        $this->assertContains('zipcodes', $tables);
    }

    public function testDistrictTableStructure(): void
    {
        $this->databaseManager->createTables();
        $pdo = $this->databaseManager->getPdo();

        // Test districts table structure
        $stmt = $pdo->query("PRAGMA table_info(districts)");
        if ($stmt === false) {
            throw new Exception('Unable to run query');
        }
        $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $columnNames = array_column($columns, 'name');
        $this->assertContains('id', $columnNames);
        $this->assertContains('name', $columnNames);

        // Check primary key
        $primaryKey = array_filter($columns, function ($column) {
            return $column['pk'] == 1;
        });

        $this->assertCount(1, $primaryKey);
        $this->assertEquals('id', array_values($primaryKey)[0]['name']);
    }

    public function testMunicipalitiesTableStructure(): void
    {
        $this->databaseManager->createTables();
        $pdo = $this->databaseManager->getPdo();

        // Test municipalities table structure
        $stmt = $pdo->query("PRAGMA table_info(municipalities)");
        if ($stmt === false) {
            throw new Exception('Unable to run query');
        }
        $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $columnNames = array_column($columns, 'name');
        $this->assertContains('id', $columnNames);
        $this->assertContains('district_id', $columnNames);
        $this->assertContains('name', $columnNames);

        // Check foreign keys
        $stmt = $pdo->query("PRAGMA foreign_key_list(municipalities)");
        if ($stmt === false) {
            throw new Exception('Unable to run query');
        }
        $foreignKeys = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertCount(1, $foreignKeys);
        $this->assertEquals('district_id', $foreignKeys[0]['from']);
        $this->assertEquals('districts', $foreignKeys[0]['table']);
        $this->assertEquals('id', $foreignKeys[0]['to']);
    }

    public function testZipcodesTableStructure(): void
    {
        $this->databaseManager->createTables();
        $pdo = $this->databaseManager->getPdo();

        // Test zipcodes table structure
        $stmt = $pdo->query("PRAGMA table_info(zipcodes)");
        if ($stmt === false) {
            throw new Exception('Unable to run query');
        }
        $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $columnNames = array_column($columns, 'name');
        $this->assertContains('id', $columnNames);
        $this->assertContains('district_id', $columnNames);
        $this->assertContains('municipality_id', $columnNames);
        $this->assertContains('zipcode', $columnNames);
        $this->assertContains('extension', $columnNames);
        $this->assertContains('postal_designation', $columnNames);

        // Check foreign keys
        $stmt = $pdo->query("PRAGMA foreign_key_list(zipcodes)");
        if ($stmt === false) {
            throw new Exception('Unable to run query');
        }
        $foreignKeys = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertCount(3, $foreignKeys);

        // Find the foreign keys
        $districtFk = null;
        $municipalityFk = null;

        foreach ($foreignKeys as $fk) {
            if ($fk['from'] === 'district_id') {
                $districtFk = $fk;
            } elseif ($fk['from'] === 'municipality_id') {
                $municipalityFk = $fk;
            }
        }

        $this->assertNotNull($districtFk, 'District foreign key not found');
        $this->assertEquals('districts', $districtFk['table']);
        $this->assertEquals('id', $districtFk['to']);
    }

    public function testZipcodesTableIndexes(): void
    {
        $this->databaseManager->createTables();
        $pdo = $this->databaseManager->getPdo();

        // Test indexes on zipcodes table
        $stmt = $pdo->query("PRAGMA index_list(zipcodes)");
        if ($stmt === false) {
            throw new Exception('Unable to run query');
        }
        $indexes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Extract index names
        $indexNames = array_column($indexes, 'name');

        $this->assertContains('idx_zipcode', $indexNames, 'Zipcode index not found');
        $this->assertContains('idx_locality', $indexNames, 'Locality index not found');
        $this->assertContains('idx_street', $indexNames, 'Street index not found');
        $this->assertContains('idx_zip_full', $indexNames, 'Composite zipcode+extension index not found');

        // Test each index's columns
        $indexColumns = [
            'idx_zipcode' => ['zipcode'],
            'idx_locality' => ['locality_name'],
            'idx_street' => ['street_name'],
            'idx_zip_full' => ['zipcode', 'extension'],
        ];

        foreach ($indexColumns as $indexName => $expectedColumns) {
            $stmt = $pdo->query("PRAGMA index_info($indexName)");
            if ($stmt === false) {
                throw new Exception('Unable to run query');
            }
            $info = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $actualColumns = array_column($info, 'name');
            $this->assertEquals($expectedColumns, $actualColumns, "Index $indexName should be on columns " . implode(', ', $expectedColumns));
        }
    }
}
