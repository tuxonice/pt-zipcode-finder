# Portugal Zipcode Finder

A PHP package for searching Portugal zipcodes using a SQLite database. This package provides tools to import zipcode data from CSV files and perform searches based on zipcode, locality, or street name.

## Features

- SQLite database for efficient storage and querying
- Symfony Console commands for easy interaction
- Import data from standard Portugal zipcode CSV files
- Search by zipcode, locality, or street name
- Detailed results with district and municipality information
- Facade implementation for programmatic usage

## Installation

```bash
composer require tuxonice/pt-zipcode-finder
```

## Usage

### Importing Data

Before you can search for zipcodes, you need to import the data from the CSV files:

```bash
# Using the default directory 'todos_cp'
php bin/pt-zipcode-finder import

# Specifying a custom directory
php bin/pt-zipcode-finder import /path/to/csv/files

# Specifying a custom database location
php bin/pt-zipcode-finder import --database=/path/to/database.sqlite
```

### Searching for Zipcodes

Once the data is imported, you can search for zipcodes:

```bash
# Search by any field (zipcode, locality, street)
php bin/pt-zipcode-finder search "lisboa"

# Search specifically by zipcode
php bin/pt-zipcode-finder search "1000" --type=zipcode

# Search specifically by locality
php bin/pt-zipcode-finder search "porto" --type=locality

# Search specifically by street name
php bin/pt-zipcode-finder search "liberdade" --type=street
```

### Using the ZipcodeFinder Facade

You can also use the ZipcodeFinder facade in your PHP code for programmatic access to the zipcode data:

```php
<?php

use Tuxonice\PtZipcodeFinder\Facade\ZipcodeFinder;

// Initialize the facade with the default database path
$finder = new ZipcodeFinder();

// Or specify a custom database path
// $finder = new ZipcodeFinder('/path/to/database.sqlite');

// Search by zipcode
$zipcodeResults = $finder->searchByZipcode('1000');

// Search by locality
$localityResults = $finder->searchByLocality('porto');

// Search by street name
$streetResults = $finder->searchByStreet('liberdade');

// Search across all fields
$allResults = $finder->searchAll('lisboa');

// Process search results
foreach ($zipcodeResults as $result) {
    echo "Zipcode: {$result['zipcode']}-{$result['extension']}\n";
    echo "Location: {$result['locality_name']}\n";
    echo "District: {$result['district_name']}\n";
    echo "Municipality: {$result['municipality_name']}\n";
    echo "Street: {$result['street_name']}\n";
    echo "-------------------\n";
}
```

### Integration Example

Here's how you might integrate the ZipcodeFinder with a web application:

```php
<?php

require_once 'vendor/autoload.php';

use Tuxonice\PtZipcodeFinder\Facade\ZipcodeFinder;

// Handle form submission
if (isset($_POST['search'])) {
    $query = $_POST['query'] ?? '';
    $type = $_POST['type'] ?? 'all';
    
    $finder = new ZipcodeFinder();
    
    // Validate database existence
    if (!$finder->validateDatabase()) {
        echo "Database not found. Please run the import command first.";
        exit;
    }
    
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
    
    // Display results
    if (empty($results)) {
        echo "No results found.";
    } else {
        echo "Found " . count($results) . " results.";
        // Display results in a table or other format
        // ...
    }
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
