<?php

namespace Tests\Unit\Facade;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Tlab\PtZipcodeFinder\Facade\ZipcodeFinder;
use Tlab\PtZipcodeFinder\Database\DatabaseManager;
use Tlab\PtZipcodeFinder\Model\Zipcode;

class ZipcodeFinderTest extends TestCase
{
    private string $databasePath;

    private ZipcodeFinder $zipcodeFinder;

    protected function setUp(): void
    {
        // Create a temporary database file for testing
        $this->databasePath = sys_get_temp_dir() . '/zipcode_test_' . uniqid() . '.sqlite';
        touch($this->databasePath);

        // Create database structure
        $databaseManager = new DatabaseManager($this->databasePath);
        $databaseManager->createTables();

        // Insert test data
        $this->insertTestData($databaseManager->getPdo());

        // Create the facade instance
        $this->zipcodeFinder = new ZipcodeFinder($this->databasePath);
    }

    protected function tearDown(): void
    {
        // Clean up the test database file
        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    /**
     * Insert test data into the database for testing
     */
    private function insertTestData(\PDO $pdo): void
    {
        // Insert test districts
        $pdo->exec("INSERT INTO districts (id, name) VALUES ('01', 'Lisboa')");
        $pdo->exec("INSERT INTO districts (id, name) VALUES ('02', 'Porto')");

        // Insert test municipalities
        $pdo->exec("INSERT INTO municipalities (id, district_id, name) VALUES ('0101', '01', 'Lisboa')");
        $pdo->exec("INSERT INTO municipalities (id, district_id, name) VALUES ('0201', '02', 'Porto')");

        // Insert test zipcodes
        $pdo->exec("
            INSERT INTO zipcodes (
                district_id, municipality_id, locality_id, locality_name, 
                street_code, street_type, street_name, zipcode, extension, postal_designation
            ) VALUES (
                '01', '0101', '010101', 'Lisboa', 
                '01010101', 'Rua', 'Augusta', '1000', '001', 'LISBOA'
            )
        ");

        $pdo->exec("
            INSERT INTO zipcodes (
                district_id, municipality_id, locality_id, locality_name, 
                street_code, street_type, street_name, zipcode, extension, postal_designation
            ) VALUES (
                '01', '0101', '010101', 'Lisboa', 
                '01010102', 'Avenida', 'Liberdade', '1000', '002', 'LISBOA'
            )
        ");

        $pdo->exec("
            INSERT INTO zipcodes (
                district_id, municipality_id, locality_id, locality_name, 
                street_code, street_type, street_name, zipcode, extension, postal_designation
            ) VALUES (
                '02', '0201', '020101', 'Porto', 
                '02010101', 'Rua', 'Santa Catarina', '4000', '001', 'PORTO'
            )
        ");
    }

    public function testSearchByZipcode(): void
    {
        // Test exact match
        /** @var ArrayCollection<int,Zipcode> $results */
        $results = $this->zipcodeFinder->searchByZipcode('1000');
        $this->assertEquals(2, $results->count());
        $this->assertEquals('1000', $results->first()->getZipcode());
        $this->assertEquals('1000', $results->offsetGet(1)->getZipcode());

        // Test partial match
        $results = $this->zipcodeFinder->searchByZipcode('100');
        $this->assertEquals(2, $results->count());

        // Test with extension
        $results = $this->zipcodeFinder->searchByZipcode('1000-001');
        $this->assertGreaterThanOrEqual(1, $results->count());

        // Test no results
        $results = $this->zipcodeFinder->searchByZipcode('9999');
        $this->assertCount(0, $results);

        // Test limit
        $results = $this->zipcodeFinder->searchByZipcode('1000', 1);
        $this->assertCount(1, $results);
    }

    public function testSearchByLocality(): void
    {
        // Test exact match
        /** @var ArrayCollection<int,Zipcode> $results */
        $results = $this->zipcodeFinder->searchByLocality('Lisboa');
        $this->assertCount(2, $results);
        $this->assertEquals('Lisboa', $results->first()->getLocalityName());
        $this->assertEquals('01010101', $results->first()->getStreetCode());
        $this->assertEquals('010101', $results->first()->getLocalityId());
        $this->assertEquals('Rua', $results->first()->getStreetType());
        $this->assertNull($results->first()->getStreetTitle());
        $this->assertNull($results->first()->getFirstPrep());
        $this->assertNull($results->first()->getSecondPrep());
        $this->assertNull($results->first()->getStreetLocation());
        $this->assertNull($results->first()->getSection());
        $this->assertNull($results->first()->getDoor());
        $this->assertNull($results->first()->getClient());
        $this->assertEquals('LISBOA', $results->first()->getPostalDesignation());
        $this->assertEquals('1000-001', $results->first()->getFullZipcode());


        // Test partial match
        $results = $this->zipcodeFinder->searchByLocality('Lisb');
        $this->assertCount(2, $results);

        // Test case insensitivity
        $results = $this->zipcodeFinder->searchByLocality('lisboa');
        $this->assertCount(2, $results);

        // Test no results
        $results = $this->zipcodeFinder->searchByLocality('Faro');
        $this->assertCount(0, $results);

        // Test limit
        $results = $this->zipcodeFinder->searchByLocality('Lisboa', 1);
        $this->assertCount(1, $results);
    }

    public function testSearchByStreet(): void
    {
        // Test exact match
        /** @var ArrayCollection<int,Zipcode> $results */
        $results = $this->zipcodeFinder->searchByStreet('Augusta');
        $this->assertCount(1, $results);
        $this->assertEquals('Augusta', $results->first()->getStreetName());

        // Test partial match
        $results = $this->zipcodeFinder->searchByStreet('Aug');
        $this->assertCount(1, $results);

        // Test case insensitivity
        $results = $this->zipcodeFinder->searchByStreet('augusta');
        $this->assertCount(1, $results);

        // Test no results
        $results = $this->zipcodeFinder->searchByStreet('Nonexistent');
        $this->assertCount(0, $results);
    }

    public function testSearchAll(): void
    {
        // Test zipcode match
        $results = $this->zipcodeFinder->searchAll('1000');
        $this->assertCount(2, $results);

        // Test locality match
        $results = $this->zipcodeFinder->searchAll('Lisboa');
        $this->assertCount(2, $results);

        // Test street match
        $results = $this->zipcodeFinder->searchAll('Augusta');
        $this->assertCount(1, $results);

        // Test postal designation match
        $results = $this->zipcodeFinder->searchAll('LISBOA');
        $this->assertCount(2, $results);

        // Test no results
        $results = $this->zipcodeFinder->searchAll('Nonexistent');
        $this->assertCount(0, $results);

        // Test limit
        $results = $this->zipcodeFinder->searchAll('Lisboa', 1);
        $this->assertCount(1, $results);
    }
}
