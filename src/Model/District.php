<?php

namespace Tlab\PtZipcodeFinder\Model;

class District
{
    private string $id;
    private string $name;

    private function __construct(string $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    /**
     * @param array<string,string> $data
     * @return self
     */
    public static function createFromArray(array $data): self
    {
        return new self($data['id'], $data['name']);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
