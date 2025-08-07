<?php

namespace Tlab\PtZipcodeFinder\Command;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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
            ->addArgument('directory', InputArgument::REQUIRED, 'Directory containing CSV files')
            ->addOption('database', 'd', InputOption::VALUE_REQUIRED, 'Path to SQLite database file')
            ->addOption('truncate', 't', InputOption::VALUE_NONE, 'Truncate tables before import');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $directory = $input->getArgument('directory');
        $databasePath = $input->getOption('database');

        // Validate directory
        if (!is_dir($directory)) {
            $this->io->error("Directory '$directory' does not exist.");
            return Command::FAILURE;
        }

        if (!is_dir($databasePath)) {
            $this->io->error("Database path '$databasePath' does not exist");
            return Command::FAILURE;
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
            $result = $importer->import($directory, $input->getOption('truncate'));
            
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
