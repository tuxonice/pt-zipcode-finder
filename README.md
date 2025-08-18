# Portugal Zipcode Finder

[![PHP Tests](https://github.com/tuxonice/pt-zipcode-finder/actions/workflows/php-tests.yml/badge.svg?branch=main)](https://github.com/tuxonice/pt-zipcode-finder/actions/workflows/php-tests.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-%E2%89%A5%208.1-777bb4?logo=php)](composer.json)
[![Code Style: PSR-12](https://img.shields.io/badge/code_style-PSR--12-4baf4f.svg)](https://www.php-fig.org/psr/psr-12/)

A PHP package for searching Portugal zipcodes using a SQLite database.
This package provides tools to import zipcode data from CSV files and perform searches based on zipcode, locality, or
street name.

## Features

- SQLite database for efficient storage and querying
- Symfony Console commands for easy interaction
- Import data from standard Portugal zipcode CSV files
- Search by zipcode, locality, or street name
- Detailed results with district and municipality information
- Facade implementation for programmatic usage

## Installation

Soon...

## Usage

### Get data to import

The zipcode data can be obtained from
the [CTT website](https://appserver2.ctt.pt/feapl_2/app/open/postalCodeSearch/postalCodeSearch.jspx).
You need register and log in to access the data.

The downloaded file is a zip file containing the following files:

| File          | Description         | Rename to          |
|---------------|---------------------|--------------------|
| concelhos.txt | Municipalities list | municipalities.csv |
| distritos.txt | Districs list       | districts.csv      |
| todos_cp.txt  | Zipcodes list       | zipcodes.csv       |
| leiame.txt    | Readme file         |                    |

Extract the files from the zip file and rename them to the expected names.

### Importing Data

Before you can search for zipcodes, you need to import the data from the CSV files:

```bash
# Import from a CSV directory into a database directory
# Syntax: php bin/zipcode-importer import <csv-source-dir> <database-dir> [--dbname=zipcodes]

# Example: import CSVs from ./source-dir and create ./data-dir/zipcodes.sqlite
php bin/zipcode-importer import source-dir data-dir --dbname=zipcodes

# Using absolute paths
php bin/zipcode-importer import /abs/path/to/csv /abs/path/to/data --dbname=mydb
```

### Using the ZipcodeFinder Facade

You can use the ZipcodeFinder facade in your PHP code for programmatic access to the zipcode data:

```php
<?php

require_once 'vendor/autoload.php';

use Tlab\PtZipcodeFinder\Facade\ZipcodeFinder;

// Initialize the facade with the database path created by the import step
$finder = new ZipcodeFinder(__DIR__ . '/data/zipcodes.sqlite');

// Search by zipcode
$zipcodeResults = $finder->searchByZipcode('1000');

// Search by locality
$localityResults = $finder->searchByLocality('porto');

// Search by street name
$streetResults = $finder->searchByStreet('liberdade');

// Search across all fields
$allResults = $finder->searchAll('lisboa');

// Iterate ArrayCollection of Zipcode models
foreach ($zipcodeResults as $zipcode) {
    echo $zipcode->getFullZipcode() . ' - ' . $zipcode->getPostalDesignation() . PHP_EOL;
}
```

### Integration Example

Here's how you might integrate the ZipcodeFinder with a web application:

```php
<?php

require_once 'vendor/autoload.php';

use Tlab\PtZipcodeFinder\Facade\ZipcodeFinder;

// Handle form submission
if (isset($_POST['search'])) {
    $query = $_POST['query'] ?? '';
    $type = $_POST['type'] ?? 'all';

    $finder = new ZipcodeFinder(__DIR__ . '/data/zipcodes.sqlite');

    // Perform search based on type
    switch ($type) {
        case 'zipcode':
            $results = $finder->searchByZipcode($query);
            break;
        case 'locality':
            $results = $finder->searchByLocality($query);
            break;
        case 'street':
            $results = $finder->searchByStreet($query);
            break;
        case 'all':
        default:
            $results = $finder->searchAll($query);
            break;
    }

    // Display count
    echo 'Found ' . $results->count() . ' results';
}
```

## Data Structure

The package uses three main data files:

1. `todos_cp.txt` - Main zipcode data with 17 fields per line
2. `distritos.txt` - District codes and names
3. `concelhos.txt` - Municipality codes and names

The data format is described in detail in the `leiame.txt` file included with the data files.

## Database Schema

The package creates three tables in the SQLite database:

- `districts` - District information
- `municipalities` - Municipality information
- `zipcodes` - Main zipcode data

## Docker Environment

This package includes a Docker environment to make development and testing easier.

### Building the Docker Image

```bash
docker-compose build
```

### Running the Application

```bash
# Run the test service (installs dependencies)
docker-compose run test

# Run the app service (provides a shell)
docker-compose run app

```

### Running Unit Tests

```bash
docker-compose run app vendor/bin/phpunit

# Import data
docker-compose run app import
```

### Running Tests

```bash
docker-compose run test
```

## License

MIT
