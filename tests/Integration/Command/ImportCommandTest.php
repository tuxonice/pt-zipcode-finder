<?php

namespace Tests\Integration\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tlab\PtZipcodeFinder\Command\ImportCommand;
use Tlab\PtZipcodeFinder\Database\DatabaseManager;

class ImportCommandTest extends TestCase
{
    private string $projectCwd;
    private string $workDir;
    private string $csvDir;
    private string $dbDir;

    protected function setUp(): void
    {
        $this->projectCwd = getcwd();
        $this->workDir = $this->projectCwd . '/build/test_import_' . uniqid();
        $this->csvDir = $this->workDir . '/csv';
        $this->dbDir = $this->workDir . '/db';
        @mkdir($this->csvDir, 0777, true);
        @mkdir($this->dbDir, 0777, true);
        $this->writeBasicCsvs($this->csvDir);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->workDir);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @chmod($path, 0666);
                @unlink($path);
            }
        }
        @chmod($dir, 0777);
        @rmdir($dir);
    }

    private function writeBasicCsvs(string $dir): void
    {
        file_put_contents($dir . '/districts.csv', "01;Lisboa\n");
        file_put_contents($dir . '/municipalities.csv', "01;0101;Lisboa\n");
        // minimal valid rows compatible with importer expected columns
        $rows = [
            ['01','0101','010101','Lisboa','','','','','','Augusta','','','','','1000','001','LISBOA'],
        ];
        $fh = fopen($dir . '/zipcodes.csv', 'wb');
        foreach ($rows as $r) {
            fwrite($fh, implode(';', $r) . "\n");
        }
        fclose($fh);
    }

    private function buildCommandTester(): CommandTester
    {
        $application = new Application('pt-zipcode-finder-tests');
        $application->add(new ImportCommand());
        $command = $application->find('import');
        return new CommandTester($command);
    }

    public function testSuccessfulImportAbsolutePaths(): void
    {
        $tester = $this->buildCommandTester();
        $status = $tester->execute([
            'source' => $this->csvDir,
            'database' => $this->dbDir,
            '--dbname' => 'testdb',
        ]);
        $this->assertSame(0, $status, $tester->getDisplay());

        $dbFile = $this->dbDir . '/testdb.sqlite';
        $this->assertFileExists($dbFile);

        $db = new DatabaseManager($dbFile);
        $pdo = $db->getPdo();
        $count = (int) $pdo->query('SELECT COUNT(*) FROM zipcodes')->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testRelativePathsNormalization(): void
    {
        $tester = $this->buildCommandTester();
        $relativeSource = '.' . substr($this->csvDir, strlen($this->projectCwd));
        $relativeDb = '.' . substr($this->dbDir, strlen($this->projectCwd));

        $status = $tester->execute([
            'source' => $relativeSource,
            'database' => $relativeDb,
            '--dbname' => 'rel',
        ]);
        $this->assertSame(0, $status, $tester->getDisplay());
        $this->assertFileExists($this->dbDir . '/rel.sqlite');
    }

    public function testMissingSourceDirectoryFails(): void
    {
        $tester = $this->buildCommandTester();
        $status = $tester->execute([
            'source' => $this->workDir . '/nope',
            'database' => $this->dbDir,
        ]);
        $this->assertSame(1, $status);
        // Implementation reports the first missing CSV file message; allow console formatting
        $this->assertStringContainsString('municipalities.csv', $tester->getDisplay());
    }

    public function testFailsWhenCsvMissing(): void
    {
        // Remove a CSV to trigger importer failure
        @unlink($this->csvDir . '/zipcodes.csv');
        $tester = $this->buildCommandTester();
        $status = $tester->execute([
            'source' => $this->csvDir,
            'database' => $this->dbDir,
            '--dbname' => 'missing',
        ]);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('zipcodes.csv', $tester->getDisplay());
    }
}
