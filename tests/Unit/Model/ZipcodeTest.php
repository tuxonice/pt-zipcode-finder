<?php

namespace Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Tlab\PtZipcodeFinder\Model\District;
use Tlab\PtZipcodeFinder\Model\Municipality;
use Tlab\PtZipcodeFinder\Model\Zipcode;

class ZipcodeTest extends TestCase
{
    public function testZipcodeCreation(): void
    {
        $zipcodeData = [
            'locality_id' => '010101',
            'locality_name' => 'Aguada de Baixo',
            'street_code' => '123',
            'street_type' => 'Rua',
            'first_prep' => 'de',
            'street_title' => 'Dr.',
            'second_prep' => 'da',
            'street_name' => 'Silva',
            'street_location' => 'Centro',
            'section' => 'A',
            'door' => '10',
            'client' => 'Empresa XYZ',
            'zipcode' => '3750',
            'extension' => '021',
            'postal_designation' => 'AGUADA DE BAIXO',
            'district_id' => '01',
            'district_name' => 'Aveiro',
            'municipality_id' => '0101',
            'municipality_name' => 'Águeda'
        ];

        $zipcode = Zipcode::createFromArray($zipcodeData);

        // Test basic properties
        $this->assertEquals('010101', $zipcode->getLocalityId());
        $this->assertEquals('Aguada de Baixo', $zipcode->getLocalityName());
        $this->assertEquals('123', $zipcode->getStreetCode());
        $this->assertEquals('Rua', $zipcode->getStreetType());
        $this->assertEquals('de', $zipcode->getFirstPrep());
        $this->assertEquals('Dr.', $zipcode->getStreetTitle());
        $this->assertEquals('da', $zipcode->getSecondPrep());
        $this->assertEquals('Silva', $zipcode->getStreetName());
        $this->assertEquals('Centro', $zipcode->getStreetLocation());
        $this->assertEquals('A', $zipcode->getSection());
        $this->assertEquals('10', $zipcode->getDoor());
        $this->assertEquals('Empresa XYZ', $zipcode->getClient());
        $this->assertEquals('3750', $zipcode->getZipcode());
        $this->assertEquals('021', $zipcode->getExtension());
        $this->assertEquals('AGUADA DE BAIXO', $zipcode->getPostalDesignation());

        // Test full zipcode formatting
        $this->assertEquals('3750-021', $zipcode->getFullZipcode());

        // Test related objects
        $district = $zipcode->getDistrict();
        $this->assertInstanceOf(District::class, $district);
        $this->assertEquals('01', $district->getId());
        $this->assertEquals('Aveiro', $district->getName());

        $municipality = $zipcode->getMunicipality();
        $this->assertInstanceOf(Municipality::class, $municipality);
        $this->assertEquals('0101', $municipality->getId());
        $this->assertEquals('01', $municipality->getDistrictId());
        $this->assertEquals('Águeda', $municipality->getName());
    }
}
