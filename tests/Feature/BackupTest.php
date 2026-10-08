<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;
use ZipArchive;

class BackupTest extends TestCase
{
    public function test_database_and_uploads_restore_from_a_checksummed_private_archive(): void
    {
        $directory = sys_get_temp_dir().'/askonce-backup-test-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($directory);
        $originalStorage = storage_path();
        $database = $directory.'/source.sqlite';
        touch($database);
        config(['database.default' => 'backup_test', 'database.connections.backup_test' => ['driver' => 'sqlite', 'database' => $database, 'prefix' => ''], 'app.backup_disk' => null]);
        $connection = DB::connection();
        $connection->statement('CREATE TABLE sample (value TEXT)');
        $connection->table('sample')->insert(['value' => 'Preserved']);
        Storage::fake('local');
        Storage::disk('local')->put('organizations/1/requests/1/example.txt', 'File content');
        $this->app->useStoragePath($directory.'/storage');
        try {
            $this->artisan('askonce:backup')->assertSuccessful();
            $archive = File::glob($directory.'/storage/app/backups/*.zip')[0];
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($archive));
            $manifest = json_decode($zip->getFromName('manifest.json'), true, 512, JSON_THROW_ON_ERROR);
            foreach ($manifest['checksums'] as $name => $hash) {
                $this->assertSame($hash, hash('sha256', $zip->getFromName($name)));
            }
            $restored = $directory.'/restored.sqlite';
            file_put_contents($restored, $zip->getFromName('database.sqlite'));
            $this->assertSame('File content', $zip->getFromName('uploads/organizations/1/requests/1/example.txt'));
            $pdo = new PDO('sqlite:'.$restored);
            $this->assertSame('ok', $pdo->query('PRAGMA integrity_check')->fetchColumn());
            $this->assertSame('Preserved', $pdo->query('SELECT value FROM sample')->fetchColumn());
            $this->assertSame(0600, fileperms($archive) & 0777);
            $zip->close();
        } finally {
            $this->app->useStoragePath($originalStorage);
            DB::purge('backup_test');
            File::deleteDirectory($directory);
        }
    }
}
