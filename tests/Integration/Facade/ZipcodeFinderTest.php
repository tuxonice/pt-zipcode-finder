<?php

namespace Tests\Integration\Facade;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Tlab\PtZipcodeFinder\Database\DatabaseManager;
use Tlab\PtZipcodeFinder\Facade\ZipcodeFinder;
use Tlab\PtZipcodeFinder\Model\Zipcode;

class ZipcodeFinderTest extends TestCase
{
    private string $databasePath;
    private ZipcodeFinder $zipcodeFinder;

    protected function setUp(): void
    {
        // Create a temporary database file for testing
        $this->databasePath = sys_get_temp_dir() . '/zipcode_integration_test_' . uniqid() . '.sqlite';
        touch($this->databasePath);

        // Create database structure
        $databaseManager = new DatabaseManager($this->databasePath);
        $databaseManager->createTables();

        // Insert test data that's more representative of real data
        $this->insertIntegrationTestData($databaseManager->getPdo());

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
     * Insert more comprehensive test data for integration testing
     */
    private function insertIntegrationTestData(\PDO $pdo): void
    {
        // Insert districts
        $districts = [
            ['11', 'Lisboa'],
            ['13', 'Porto'],
            ['08', 'Faro']
        ];

        $districtStmt = $pdo->prepare("INSERT INTO districts (id, name) VALUES (?, ?)");
        foreach ($districts as $district) {
            $districtStmt->execute($district);
        }

        // Insert municipalities
        $municipalities = [
            ['1106', '11', 'Lisboa'],
            ['1312', '13', 'Porto'],
            ['0805', '08', 'Albufeira']
        ];

        $municipalityStmt = $pdo->prepare("INSERT INTO municipalities (id, district_id, name) VALUES (?, ?, ?)");
        foreach ($municipalities as $municipality) {
            $municipalityStmt->execute($municipality);
        }

        // Insert zipcodes
        $zipcodes = [
            // Lisboa zipcodes
            [
                '11', '1106', '110603', 'Lisboa', '11060301', 'Rua', 'Augusta',
                'Centro', 'Bloco A', '10', 'Apartamento 1', '1100', '001', 'LISBOA'
            ],
            [
                '11', '1106', '110603', 'Lisboa', '11060302', 'Avenida', 'Liberdade',
                'Centro', 'Bloco B', '20', 'Loja 2', '1100', '002', 'LISBOA'
            ],
            [
                '11', '1106', '110603', 'Lisboa', '11060303', 'Praça', 'Comércio',
                'Baixa', null, '30', null, '1100', '003', 'LISBOA'
            ],

            // Porto zipcodes
            [
                '13', '1312', '131201', 'Porto', '13120101', 'Rua', 'Santa Catarina',
                'Centro', null, '40', null, '4000', '001', 'PORTO'
            ],
            [
                '13', '1312', '131201', 'Porto', '13120102', 'Avenida', 'Aliados',
                'Centro', null, '50', null, '4000', '002', 'PORTO'
            ],
            [
                '13', '1312', '131201', 'Porto', '13120102', 'Rua', 'Aliados',
                'Centro', null, '50', null, '4001', '003', 'PORTO'
            ],

            // Albufeira zipcodes
            [
                '08', '0805', '080501', 'Albufeira', '08050101', 'Rua', 'da Praia',
                'Centro', null, '60', null, '8200', '001', 'ALBUFEIRA'
            ]
        ];

        $zipcodeStmt = $pdo->prepare("
            INSERT INTO zipcodes (
                district_id, municipality_id, locality_id, locality_name, 
                street_code, street_type, street_name, 
                street_location, section, door, client,
                zipcode, extension, postal_designation
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($zipcodes as $zipcode) {
            $zipcodeStmt->execute($zipcode);
        }
    }

    public function testIntegrationSearchByZipcode(): void
    {
        // Test exact match
        $results = $this->zipcodeFinder->searchByZipcode('1100');
        $this->assertCount(3, $results);

        // Test with extension
        /** @var ArrayCollection<int,Zipcode> $results */
        $results = $this->zipcodeFinder->searchByZipcode('1100-001');
        $this->assertCount(1, $results);
        $this->assertEquals('1100', $results->first()->getZipcode());
        $this->assertEquals('001', $results->first()->getExtension());

        // Test partial match
        $results = $this->zipcodeFinder->searchByZipcode('4000');
        $this->assertCount(2, $results);

        // Test with limit
        $results = $this->zipcodeFinder->searchByZipcode('1100', 2);
        $this->assertCount(2, $results);
    }

    public function testIntegrationSearchByLocality(): void
    {
        // Test exact match
        $results = $this->zipcodeFinder->searchByLocality('Lisboa');
        $this->assertCount(3, $results);

        // Test partial match
        $results = $this->zipcodeFinder->searchByLocality('Port');
        $this->assertCount(3, $results);

        // Test with limit
        $results = $this->zipcodeFinder->searchByLocality('Lisboa', 2);
        $this->assertCount(2, $results);
    }

    public function testIntegrationSearchByStreet(): void
    {
        // Test exact match
        /** @var ArrayCollection<int,Zipcode> $results */
        $results = $this->zipcodeFinder->searchByStreet('Augusta');
        $this->assertCount(1, $results);
        $this->assertEquals('Augusta', $results->first()->getStreetName());

        // Test street type
        $results = $this->zipcodeFinder->searchByStreet('Aliados');
        $this->assertCount(2, $results);

        // Test with limit
        $results = $this->zipcodeFinder->searchByStreet('Aliados', 1);
        $this->assertCount(1, $results);
    }

    public function testIntegrationSearchAll(): void
    {
        // Test multiple fields
        $results = $this->zipcodeFinder->searchAll('Lisboa');
        $this->assertGreaterThanOrEqual(3, count($results));

        // Test postal designation
        $results = $this->zipcodeFinder->searchAll('PORTO');
        $this->assertGreaterThanOrEqual(2, count($results));

        // Test street name
        $results = $this->zipcodeFinder->searchAll('Augusta');
        $this->assertCount(1, $results);

        // Test with limit
        $results = $this->zipcodeFinder->searchAll('Centro', 3);
        $this->assertLessThanOrEqual(3, count($results));
    }

    public function testIntegrationJoinedFields(): void
    {
        // Test that district and municipality names are correctly joined
        /** @var ArrayCollection<int,Zipcode> $results */
        $results = $this->zipcodeFinder->searchByZipcode('1100-001');
        $this->assertCount(1, $results);
        $this->assertEquals('Lisboa', $results->first()->getDistrict()->getName());
        $this->assertEquals('Lisboa', $results->first()->getMunicipality()->getName());

        /** @var ArrayCollection<int,Zipcode> $results */
        $results = $this->zipcodeFinder->searchByZipcode('4000-001');
        $this->assertCount(1, $results);
        $this->assertEquals('Porto', $results->first()->getDistrict()->getName());
        $this->assertEquals('Porto', $results->first()->getMunicipality()->getName());
    }
}
