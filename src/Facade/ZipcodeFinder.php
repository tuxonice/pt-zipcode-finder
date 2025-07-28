<?php

namespace Tlab\PtZipcodeFinder\Facade;

use Doctrine\Common\Collections\ArrayCollection;
use PDO;
use Tlab\PtZipcodeFinder\Database\DatabaseManager;
use Tlab\PtZipcodeFinder\Model\Zipcode;

/**
 * ZipcodeFinder Facade
 *
 * This facade provides a clean interface to search for zipcode information
 * using various criteria like zipcode, locality, and street name.
 */
class ZipcodeFinder
{
    private PDO $pdo;

    /**
     * Constructor
     *
     * @param string $databaseFilePath Path to the database file
     */
    public function __construct(string $databaseFilePath)
    {
        $databaseManager = new DatabaseManager($databaseFilePath);
        $this->pdo = $databaseManager->getPdo();
    }

    /**
     * Search by zipcode
     *
     * @param string $query The zipcode to search for
     * @param int $limit Maximum number of results to return
     * @return ArrayCollection<int,Zipcode> Search results
     */
    public function searchByZipcode(string $query, int $limit = 100): ArrayCollection
    {
        $collection = new ArrayCollection();
        $stmt = $this->pdo->prepare('
            SELECT z.*, d.name as district_name, m.name as municipality_name
            FROM zipcodes z
            JOIN districts d ON z.district_id = d.id
            JOIN municipalities m ON z.municipality_id = m.id AND z.district_id = m.district_id
            WHERE z.zipcode LIKE :query COLLATE NOCASE OR z.zipcode || \'-\' || z.extension LIKE :query COLLATE NOCASE
            LIMIT :limit
        ');

        $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($records as $record) {
            $collection->add(Zipcode::createFromArray($record));
        }

        return $collection;
    }

    /**
     * Search by locality name
     *
     * @param string $query The locality name to search for
     * @param int $limit Maximum number of results to return
     * @return ArrayCollection<int,Zipcode> Search results
     */
    public function searchByLocality(string $query, int $limit = 100): ArrayCollection
    {
        $collection = new ArrayCollection();
        $stmt = $this->pdo->prepare('
            SELECT z.*, d.name as district_name, m.name as municipality_name
            FROM zipcodes z
            JOIN districts d ON z.district_id = d.id
            JOIN municipalities m ON z.municipality_id = m.id AND z.district_id = m.district_id
            WHERE z.locality_name LIKE :query COLLATE NOCASE
            LIMIT :limit
        ');

        $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($records as $record) {
            $collection->add(Zipcode::createFromArray($record));
        }

        return $collection;
    }

    /**
     * Search by street name
     *
     * @param string $query The street name to search for
     * @param int $limit Maximum number of results to return
     * @return ArrayCollection<int,Zipcode> Search results
     */
    public function searchByStreet(string $query, int $limit = 100): ArrayCollection
    {
        $collection = new ArrayCollection();
        $stmt = $this->pdo->prepare('
            SELECT z.*, d.name as district_name, m.name as municipality_name
            FROM zipcodes z
            JOIN districts d ON z.district_id = d.id
            JOIN municipalities m ON z.municipality_id = m.id AND z.district_id = m.district_id
            WHERE z.street_name LIKE :query COLLATE NOCASE
            LIMIT :limit
        ');

        $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($records as $record) {
            $collection->add(Zipcode::createFromArray($record));
        }

        return $collection;
    }

    /**
     * Search across all fields (zipcode, locality name, street name, postal designation)
     *
     * @param string $query The query to search for
     * @param int $limit Maximum number of results to return
     * @return ArrayCollection<int,Zipcode> Search results
     */
    public function searchAll(string $query, int $limit = 100): ArrayCollection
    {
        $collection = new ArrayCollection();
        $stmt = $this->pdo->prepare('
            SELECT z.*, d.name as district_name, m.name as municipality_name
            FROM zipcodes z
            JOIN districts d ON z.district_id = d.id
            JOIN municipalities m ON z.municipality_id = m.id AND z.district_id = m.district_id
            WHERE 
                z.zipcode LIKE :query COLLATE NOCASE OR 
                z.zipcode || \'-\' || z.extension LIKE :query COLLATE NOCASE OR
                z.locality_name LIKE :query COLLATE NOCASE OR
                z.street_name LIKE :query COLLATE NOCASE OR
                z.postal_designation LIKE :query COLLATE NOCASE
            LIMIT :limit
        ');

        $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($records as $record) {
            $collection->add(Zipcode::createFromArray($record));
        }

        return $collection;
    }
}
