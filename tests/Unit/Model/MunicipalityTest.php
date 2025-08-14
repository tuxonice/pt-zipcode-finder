<?php

namespace Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Tlab\PtZipcodeFinder\Model\Municipality;

class MunicipalityTest extends TestCase
{
    public function testMunicipalityCreation(): void
    {
        $municipality = Municipality::createFromArray(
            [
                'id' => '0101',
                'district_id' => '01',
                'name' => 'Águeda'
            ]
        );

        $this->assertEquals('0101', $municipality->getId());
        $this->assertEquals('01', $municipality->getDistrictId());
        $this->assertEquals('Águeda', $municipality->getName());
    }
}
