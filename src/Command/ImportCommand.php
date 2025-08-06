<?php

namespace Tlab\PtZipcodeFinder\Command;

use PDO;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tlab\PtZipcodeFinder\Database\DatabaseManager;

class ImportCommand extends Command
{
    protected static $defaultName = 'import';
    protected static $defaultDescription = 'Import Portugal zipcode data from CSV files';

    private DatabaseManager $databaseManager;
    private PDO $pdo;
    private SymfonyStyle $io;

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addArgument('directory', InputArgument::REQUIRED, 'Directory containing CSV files')
            ->addOption('database', 'd', InputOption::VALUE_REQUIRED, 'Path to SQLite database file')
            ->addOption('truncate', 't', InputOption::VALUE_NONE, 'Truncate tables before import');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $directory = $input->getArgument('directory');
        dump($directory);
        $databasePath = $input->getOption('database');
        dump($databasePath);

        // Validate directory
        if (!is_dir($directory)) {
            $this->io->error("Directory '$directory' does not exist.");
            return Command::FAILURE;
        }

        if (!is_dir($databasePath)) {
            $this->io->error("Database path '$directory' does not exist");
            return Command::FAILURE;
        }

        // Initialize database
        $this->databaseManager = new DatabaseManager($databasePath);
        $this->pdo = $this->databaseManager->getPdo();

        // Create tables
        $this->io->section('Creating database tables');
        $this->databaseManager->createTables();
        $this->io->success('Database tables created successfully');

        // Truncate tables if option is set
        if ($input->getOption('truncate')) {
            $this->io->section('Truncating database tables');
            $this->truncateTables();
            $this->io->success('Database tables truncated successfully');
        }

        // Import data
        try {
            $this->importDistricts($directory);
            $this->importMunicipalities($directory);
            $this->importZipcodes($directory);
            $this->io->success('All data imported successfully');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->io->error('Error importing data: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function importDistricts(string $directory): void
    {
        $filePath = $directory . '/distritos.txt';
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Districts file not found: $filePath");
        }

        $this->io->section('Importing districts');
        $stmt = $this->pdo->prepare('INSERT INTO districts (id, name) VALUES (?, ?)');

        $count = 0;
        $file = fopen($filePath, 'r');
        if (!$file) {
            throw new RuntimeException('Error open districts file');
        }
        while (($line = fgets($file)) !== false) {
            // Detect encoding and convert to UTF-8 if needed
            $encoding = mb_detect_encoding($line, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
            if ($encoding === false) {
                $this->io->warning('Unable to detect encoding. Skipping line: ' . $line);

                continue;
            }
            if ($encoding !== 'UTF-8') {
                $line = mb_convert_encoding($line, 'UTF-8', $encoding);
            }

            $data = str_getcsv(trim($line), ';');
            if (count($data) !== 2) {
                $this->io->warning("Invalid district data: " . implode(';', $data));
                continue;
            }

            $stmt->execute([$data[0], $data[1]]);
            $count++;
        }
        fclose($file);

        $this->io->success("Imported $count districts");
    }

    private function importMunicipalities(string $directory): void
    {
        $filePath = $directory . '/concelhos.txt';
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Municipalities file not found: $filePath");
        }

        $this->io->section('Importing municipalities');
        $stmt = $this->pdo->prepare('INSERT INTO municipalities (district_id, id, name) VALUES (?, ?, ?)');

        $count = 0;
        $file = fopen($filePath, 'r');
        if (!$file) {
            throw new RuntimeException('Error open municipalities file');
        }
        while (($line = fgets($file)) !== false) {
            // Detect encoding and convert to UTF-8 if needed
            $encoding = mb_detect_encoding($line, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
            if ($encoding !== 'UTF-8') {
                $line = mb_convert_encoding($line, 'UTF-8', $encoding);
            }

            $data = str_getcsv(trim($line), ';');
            if (count($data) !== 3) {
                $this->io->warning("Invalid municipality data: " . implode(';', $data));
                continue;
            }

            $stmt->execute([$data[0], $data[1], $data[2]]);
            $count++;
        }
        fclose($file);

        $this->io->success("Imported $count municipalities");
    }

    /**
     * Truncate all tables in reverse order to respect foreign key constraints
     */
    private function truncateTables(): void
    {
        // Disable foreign key checks if your database supports it
        $this->pdo->exec('PRAGMA foreign_keys = OFF');

        // Truncate in reverse order of dependencies
        $this->pdo->exec('DELETE FROM zipcodes');
        $this->pdo->exec('DELETE FROM municipalities');
        $this->pdo->exec('DELETE FROM districts');

        // Reset auto-increment counters if your database uses them
        $this->pdo->exec('DELETE FROM sqlite_sequence WHERE name IN ("zipcodes", "municipalities", "districts")');

        // Re-enable foreign key checks
        $this->pdo->exec('PRAGMA foreign_keys = ON');
    }

    private function importZipcodes(string $directory): void
    {
        $filePath = $directory . '/todos_cp.txt';
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Zipcodes file not found: $filePath");
        }

        $this->io->section('Importing zipcodes');
        $stmt = $this->pdo->prepare('
            INSERT INTO zipcodes (
                district_id, municipality_id, locality_id, locality_name,
                street_code, street_type, first_prep, street_title,
                second_prep, street_name, street_location, section,
                door, client, zipcode, extension, postal_designation
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $count = 0;
        $batchSize = 1000;
        $this->pdo->beginTransaction();

        $file = fopen($filePath, 'r');
        $progressBar = $this->io->createProgressBar();
        $progressBar->start();

        while (($line = fgets($file)) !== false) {
            // Detect encoding and convert to UTF-8 if needed
            $encoding = mb_detect_encoding($line, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
            if ($encoding !== 'UTF-8') {
                $line = mb_convert_encoding($line, 'UTF-8', $encoding);
            }

            $data = str_getcsv(trim($line), ';');
            if (count($data) !== 17) {
                $this->io->warning("Invalid zipcode data: " . implode(';', $data));
                continue; // Skip invalid lines
            }

            // Empty strings to null for optional fields
            for ($i = 4; $i <= 13; $i++) {
                if ($data[$i] === '') {
                    $data[$i] = null;
                }
            }

            $stmt->execute($data);
            $count++;

            if ($count % $batchSize === 0) {
                $this->pdo->commit();
                $this->pdo->beginTransaction();
                $progressBar->advance($batchSize);
            }
        }

        $this->pdo->commit();
        fclose($file);
        $progressBar->finish();
        $this->io->newLine(2);

        $this->io->success("Imported $count zipcodes");
    }
}
