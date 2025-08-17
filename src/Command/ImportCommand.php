<?php

namespace Tlab\PtZipcodeFinder\Command;

use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tlab\PtZipcodeFinder\Importer\ZipcodeImporter;

#[AsCommand(name: 'import')]
class ImportCommand extends Command
{
    private const DEFAULT_DATABASE_NAME = 'zipcodes';

    private const DEFAULT_DATABASE_NAME_EXTENSION = '.sqlite';

    protected static $defaultName = 'import';

    protected static $defaultDescription = 'Import Portugal zipcode data from CSV files';

    private SymfonyStyle $io;

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addArgument('source', InputArgument::REQUIRED, 'Directory containing CSV files')
            ->addArgument('database', InputArgument::REQUIRED, 'Directory for SQLite database file')
            ->addOption('dbname', null, InputOption::VALUE_OPTIONAL, 'Database name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $sourceFolder = (string) $input->getArgument('source');
        $databaseFolder = (string) $input->getArgument('database');
        $databaseName = $input->getOption('dbname');

        // Normalize paths: if relative, make relative to current working directory
        $currentWorkingDir = getcwd();
        if (!str_starts_with($sourceFolder, DIRECTORY_SEPARATOR)) {
            $sourceFolder = $currentWorkingDir . DIRECTORY_SEPARATOR . $sourceFolder;
        }
        if (!str_starts_with($databaseFolder, DIRECTORY_SEPARATOR)) {
            $databaseFolder = $currentWorkingDir . DIRECTORY_SEPARATOR . $databaseFolder;
        }
        $sourceFolder = rtrim($sourceFolder, DIRECTORY_SEPARATOR);
        $databaseFolder = rtrim($databaseFolder, DIRECTORY_SEPARATOR);
        $databaseFileName = ($databaseName ?? self::DEFAULT_DATABASE_NAME) . self::DEFAULT_DATABASE_NAME_EXTENSION;

        if (!$this->verifySourceFolder($sourceFolder)) {
            return Command::FAILURE;
        }

        // Ensure database path is provided
        if (!$this->verifyDatabaseFolder($databaseFolder)) {
            return Command::FAILURE;
        }

        if (!$this->createDatabaseFile($databaseFolder, $databaseFileName)) {
            return Command::FAILURE;
        }

        // Create the importer with a logger callback
        $importer = new ZipcodeImporter();
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
            $result = $importer->import($sourceFolder, $databaseFolder . DIRECTORY_SEPARATOR . $databaseFileName);

            if ($result) {
                $this->io->success(sprintf(
                    'Import completed: %d districts, %d municipalities, %d zipcodes, %d failed',
                    $importer->getImportedDistrictsCount(),
                    $importer->getImportedMunicipalitiesCount(),
                    $importer->getImportedZipcodesCount(),
                    $importer->getFailedImportRows(),
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
        foreach ($files as $file => $path) {
            if (!file_exists($path) || !is_readable($path)) {
                $this->io->error('Unable to find ' . $file . ' file');

                return false;
            }
        }

        return true;
    }

    private function verifyDatabaseFolder(string $databasePath): bool
    {
        if (!is_dir($databasePath)) {
            $this->io->error('The directory "' . $databasePath . '" does not exist');

            return false;
        }

        if (!is_writable($databasePath)) {
            $this->io->error('The directory "' . $databasePath . '" is not writable');

            return false;
        }

        return true;
    }

    private function createDatabaseFile(string $databasePath, string $databaseName): bool
    {
        if (file_exists($databasePath . DIRECTORY_SEPARATOR . $databaseName)) {
            $this->io->error('Database file ' . $databaseName . ' already exists! Delete it first');

            return false;
        }

        if (!touch($databasePath . DIRECTORY_SEPARATOR . $databaseName)) {
            $this->io->error('Unable to create database file ' . $databaseName);

            return false;
        }

        return true;
    }
}
