<?php

namespace Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Tlab\PtZipcodeFinder\Model\District;

class DistrictTest extends TestCase
{
    public function testDistrictCreation(): void
    {
        $district = District::createFromArray(
            [
                'id' => '01',
                'name' => 'Aveiro'
            ]
        );

        $this->assertEquals('01', $district->getId());
        $this->assertEquals('Aveiro', $district->getName());
    }
}
