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
            ->addOption('directory', 'dir', InputOption::VALUE_REQUIRED, 'Directory containing CSV files')
            ->addOption('database', 'd', InputOption::VALUE_REQUIRED, 'Path to SQLite database file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $directory = $input->getOption('directory');
        $databasePath = $input->getOption('database');

        // Ensure directory path is provided
        if (!$directory) {
            $this->io->error('Directory path is required. Use --directory option to specify it.');
            return Command::FAILURE;
        }

        // Ensure database path is provided
        if (!$databasePath) {
            $this->io->error('Database path is required. Use --database option to specify it.');
            return Command::FAILURE;
        }

        // Make paths relative to current working directory if they're not absolute
        $currentWorkingDir = getcwd();

        $directory = $currentWorkingDir . DIRECTORY_SEPARATOR . $directory;
        $databasePath = $currentWorkingDir . DIRECTORY_SEPARATOR . $databasePath;

        // Validate directory
        if (!is_dir($directory)) {
            $this->io->error("Directory '$directory' does not exist.");
            return Command::FAILURE;
        }

        // Ensure database directory exists
        $databaseDir = dirname($databasePath);
        if (!is_dir($databaseDir)) {
            $this->io->note("Creating database directory: $databaseDir");
            if (!mkdir($databaseDir, 0755, true) && !is_dir($databaseDir)) {
                $this->io->error("Failed to create database directory: $databaseDir");
                return Command::FAILURE;
            }
        }

        // If database file exists, remove it for a fresh import
        if (file_exists($databasePath)) {
            $this->io->note("Removing existing database file for fresh import");
            if (!unlink($databasePath)) {
                $this->io->error("Failed to remove existing database file: $databasePath");
                return Command::FAILURE;
            }
        }

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
            $result = $importer->import($directory);
            
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
}
