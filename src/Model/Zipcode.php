<?php

namespace Tlab\PtZipcodeFinder\Model;

class Zipcode
{
    private string $localityId;

    private string $localityName;

    private ?string $streetCode = null;

    private ?string $streetType = null;

    private ?string $firstPrep = null;

    private ?string $streetTitle = null;

    private ?string $secondPrep = null;

    private ?string $streetName = null;

    private ?string $streetLocation = null;

    private ?string $section = null;

    private ?string $door = null;

    private ?string $client = null;

    private string $zipcode;

    private string $extension;

    private string $postalDesignation;

    private District $district;

    private Municipality $municipality;

    /**
     * @param array<string, string|null> $results
     */
    private function __construct(array $results)
    {
        $this->localityId = $results['locality_id'];
        $this->localityName = $results['locality_name'];
        $this->streetCode = $results['street_code'];
        $this->streetType = $results['street_type'];
        $this->firstPrep = $results['first_prep'];
        $this->streetTitle = $results['street_title'];
        $this->secondPrep = $results['second_prep'];
        $this->streetName = $results['street_name'];
        $this->streetLocation = $results['street_location'];
        $this->section = $results['section'];
        $this->door = $results['door'];
        $this->client = $results['client'];
        $this->zipcode = $results['zipcode'];
        $this->extension = $results['extension'];
        $this->postalDesignation = $results['postal_designation'];
    }

    /**
     * @param array<string,string> $results
     * @return self
     */
    public static function createFromArray(array $results): self
    {
        $object = new self($results);
        $object->setDistrict(District::createFromArray(
            [
                'id' => $results['district_id'],
                'name' => $results['district_name'],
            ]
        ));

        $object->setMunicipality(Municipality::createFromArray(
            [
                'id' => $results['municipality_id'],
                'district_id' => $results['district_id'],
                'name' => $results['municipality_name'],
            ]
        ));

        return $object;
    }

    public function getDistrict(): District
    {
        return $this->district;
    }

    public function getMunicipality(): Municipality
    {
        return $this->municipality;
    }

    private function setDistrict(District $district): self
    {
        $this->district = $district;

        return $this;
    }

    public function setMunicipality(Municipality $municipality): self
    {
        $this->municipality = $municipality;

        return $this;
    }

    public function getLocalityId(): string
    {
        return $this->localityId;
    }

    public function getLocalityName(): string
    {
        return $this->localityName;
    }

    public function getStreetCode(): ?string
    {
        return $this->streetCode;
    }

    public function getStreetType(): ?string
    {
        return $this->streetType;
    }

    public function getFirstPrep(): ?string
    {
        return $this->firstPrep;
    }

    public function getStreetTitle(): ?string
    {
        return $this->streetTitle;
    }

    public function getSecondPrep(): ?string
    {
        return $this->secondPrep;
    }

    public function getStreetName(): ?string
    {
        return $this->streetName;
    }

    public function getStreetLocation(): ?string
    {
        return $this->streetLocation;
    }

    public function getSection(): ?string
    {
        return $this->section;
    }

    public function getDoor(): ?string
    {
        return $this->door;
    }

    public function getClient(): ?string
    {
        return $this->client;
    }

    public function getZipcode(): string
    {
        return $this->zipcode;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function getPostalDesignation(): string
    {
        return $this->postalDesignation;
    }

    public function getFullZipcode(): string
    {
        return $this->zipcode . '-' . $this->extension;
    }
}
