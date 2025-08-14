<?php

namespace Tlab\PtZipcodeFinder\Command;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tlab\PtZipcodeFinder\Importer\ZipcodeImporter;

class ImportCommand extends Command
{
    protected static $defaultName = 'import';
    protected static $defaultDescription = 'Import Portugal zipcode data from CSV files';

    private SymfonyStyle $io;

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addOption('source', 's', InputOption::VALUE_REQUIRED, 'Directory containing CSV files')
            ->addOption('database', 'd', InputOption::VALUE_REQUIRED, 'Directory for SQLite database file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $sourceFolder = $input->getOption('source');
        $databasePath = $input->getOption('database');

        // Make paths relative to current working directory if they're not absolute
        $currentWorkingDir = getcwd();

        $sourceFolder = realpath($currentWorkingDir . DIRECTORY_SEPARATOR . $sourceFolder);
        $databasePath = realpath($currentWorkingDir . DIRECTORY_SEPARATOR . $databasePath);

        // Ensure directory path is provided
        if (!$sourceFolder) {
            $this->io->error('Source directory is required. Use --source option to specify it.');

            return Command::FAILURE;
        }

        // Ensure database path is provided
        if (!$databasePath) {
            $this->io->error('Database directory is required. Use --database option to specify it.');

            return Command::FAILURE;
        }

        // Validate directory
        if (!$this->verifySourceFolder($sourceFolder)) {
//            $this->io->error("Directory '$sourceFolder' does not exist");

            return Command::FAILURE;
        }

        // Ensure database directory exists
//        if (!$this->verifyDatabaseFolder($databasePath)) {
//            $this->io->note("Creating database directory: $databaseDir");
//            if (!mkdir($databaseDir, 0755, true) && !is_dir($databaseDir)) {
//                $this->io->error("Failed to create database directory: $databaseDir");
//                return Command::FAILURE;
//            }
//        }

        // If database file exists, remove it for a fresh import
//        if (file_exists($databasePath)) {
//            $this->io->note("Removing existing database file for fresh import");
//            if (!unlink($databasePath)) {
//                $this->io->error("Failed to remove existing database file: $databasePath");
//                return Command::FAILURE;
//            }
//        }

        // Create the importer with a logger callback
        $importer = new ZipcodeImporter($databasePath);
        $importer->setLogger(function (string $message, string $type) {
            switch ($type) {
                case 'info':
                    $this->io->text($message);
                    break;
                case 'success':
                    $this->io->success($message);
                    break;
                case 'warning':
                    $this->io->warning($message);
                    break;
                case 'error':
                    $this->io->error($message);
                    break;
                default:
                    $this->io->text($message);
            }
        });

        // Run the import
        try {
            $result = $importer->import($sourceFolder);
            
            if ($result) {
                $this->io->success(sprintf(
                    'Import completed: %d districts, %d municipalities, %d zipcodes',
                    $importer->getImportedDistrictsCount(),
                    $importer->getImportedMunicipalitiesCount(),
                    $importer->getImportedZipcodesCount()
                ));
                return Command::SUCCESS;
            } else {
                return Command::FAILURE;
            }
        } catch (RuntimeException $e) {
            $this->io->error('Import failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function verifySourceFolder(string $sourceFolder): bool
    {
        $files = [
            'municipalities.csv' => $sourceFolder . DIRECTORY_SEPARATOR . 'municipalities.csv',
            'districts.csv' => $sourceFolder . DIRECTORY_SEPARATOR . 'districts.csv',
            'zipcodes.csv' => $sourceFolder . DIRECTORY_SEPARATOR . 'zipcodes.csv',

        ];
        foreach($files as $file => $path) {
            if (!file_exists($path) || !is_readable($path)) {
                $this->io->error('Unable to find ' . $file. ' file');

                return false;
            }
        }

        return true;
    }

    private function verifyDatabaseFolder(string $databasePath): bool
    {

        //TODO: check for database file: zipcode.sqlite
        return false;
    }
}
