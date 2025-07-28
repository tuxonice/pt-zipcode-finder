<?php

namespace Tests\Integration\Importer;

use PHPUnit\Framework\TestCase;
use Tlab\PtZipcodeFinder\Importer\ZipcodeImporter;
use Tlab\PtZipcodeFinder\Database\DatabaseManager;

class ZipcodeImporterTest extends TestCase
{
    private string $workDir;
    private string $csvDir;
    private string $dbDir;
    private string $dbFile;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir() . '/pt_zip_importer_' . uniqid();
        $this->csvDir = $this->workDir . '/csv';
        $this->dbDir = $this->workDir . '/db';
        @mkdir($this->csvDir, 0777, true);
        @mkdir($this->dbDir, 0777, true);
        $this->dbFile = $this->dbDir . '/zipcodes.sqlite';
        // DatabaseManager requires the DB file to exist
        touch($this->dbFile);
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

    public function testSuccessfulImportWithEncodingAndInvalidLines(): void
    {
        // Prepare CSVs
        $this->writeDistricts([
            ['01', 'Lisboa'],
            ['02', mb_convert_encoding('Évora', 'ISO-8859-1', 'UTF-8')], // ISO-8859-1 encoded
        ]);
        $this->writeMunicipalities([
            ['01', '0101', 'Lisboa'],
            ['02', '0201', 'Evora'],
            ['bad'], // invalid row
        ]);
        $this->writeZipcodes([
            // minimal valid row for Lisboa
            ['01','0101','010101','Lisboa','','','','','','Augusta','','','','','1000','001','LISBOA'],
            // another valid row
            ['01','0101','010101','Lisboa','','','','','','Liberdade','','','','','1000','002','LISBOA'],
            // invalid row (wrong cols)
            ['bad'],
        ]);

        $importer = new ZipcodeImporter();
        $logs = [];
        $importer->setLogger(function (string $message, string $type) use (&$logs) {
            $logs[] = [$type, $message];
        });

        $result = $importer->import($this->csvDir, $this->dbFile);
        $this->assertTrue($result);
        $this->assertSame(2, $importer->getImportedDistrictsCount());
        $this->assertSame(2, $importer->getImportedMunicipalitiesCount());
        $this->assertSame(2, $importer->getImportedZipcodesCount());
        $this->assertGreaterThanOrEqual(2, $importer->getFailedImportRows()); // one muni + one zipcode invalid

        // Sanity check from DB
        $db = new DatabaseManager($this->dbFile);
        $pdo = $db->getPdo();
        $count = (int) $pdo->query('SELECT COUNT(*) FROM zipcodes')->fetchColumn();
        $this->assertSame(2, $count);
    }

    public function testRollbackOnForeignKeyViolationDuringZipImport(): void
    {
        // Prepare CSVs where zipcodes reference a non-existent district to trigger FK error
        $this->writeDistricts([
            ['01', 'Lisboa'],
        ]);
        $this->writeMunicipalities([
            ['01', '0101', 'Lisboa'],
        ]);
        $this->writeZipcodes([
            // valid first row
            ['01','0101','010101','Lisboa','','','','','','Augusta','','','','','1000','001','LISBOA'],
            // invalid row referencing non-existent district 99 -> FK violation -> exception -> rollback
            ['99','9901','990101','Nowhere','','','','','','Nonexistent','','','','','9999','999','NOWHERE'],
        ]);

        $importer = new ZipcodeImporter();
        $ok = $importer->import($this->csvDir, $this->dbFile);
        $this->assertFalse($ok, 'Import should fail due to FK violation and rollback');

        $db = new DatabaseManager($this->dbFile);
        $pdo = $db->getPdo();
        $count = (int) $pdo->query('SELECT COUNT(*) FROM zipcodes')->fetchColumn();
        $this->assertSame(0, $count, 'No zipcodes should remain after rollback');
    }

    /**
     * @param array<array<string>> $rows
     * @return void
     */
    private function writeDistricts(array $rows): void
    {
        $path = $this->csvDir . '/districts.csv';
        $fh = fopen($path, 'wb');
        foreach ($rows as $r) {
            if (is_array($r)) {
                // Allow raw encoded second field
                $line = $r[0] . ';' . ($r[1] ?? '') . "\n";
                fwrite($fh, $line);
            } else {
                fwrite($fh, (string)$r . "\n");
            }
        }
        fclose($fh);
    }

    /**
     * @param array<array<string>> $rows
     * @return void
     */
    private function writeMunicipalities(array $rows): void
    {
        $path = $this->csvDir . '/municipalities.csv';
        $fh = fopen($path, 'wb');
        foreach ($rows as $r) {
            if (is_array($r)) {
                fwrite($fh, implode(';', $r) . "\n");
            } else {
                fwrite($fh, (string)$r . "\n");
            }
        }
        fclose($fh);
    }

    /**
     * @param array<array<string>> $rows
     * @return void
     */
    private function writeZipcodes(array $rows): void
    {
        $path = $this->csvDir . '/zipcodes.csv';
        $fh = fopen($path, 'wb');
        foreach ($rows as $r) {
            if (is_array($r)) {
                fwrite($fh, implode(';', $r) . "\n");
            } else {
                fwrite($fh, (string)$r . "\n");
            }
        }
        fclose($fh);
    }
}
