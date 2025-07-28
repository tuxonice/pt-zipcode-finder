<?php

namespace Tlab\PtZipcodeFinder\Model;

class Municipality
{
    private string $id;

    private string $districtId;

    private string $name;

    private function __construct(string $id, string $districtId, string $name)
    {
        $this->id = $id;
        $this->districtId = $districtId;
        $this->name = $name;
    }

    /**
     * @param array<string,string> $data
     * @return self
     */
    public static function createFromArray(array $data): self
    {
        return new self($data['id'], $data['district_id'], $data['name']);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDistrictId(): string
    {
        return $this->districtId;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
