<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class CreateBackup extends Command
{
    protected $signature = 'askonce:backup {--offsite-disk= : Copy the backup to a configured private filesystem disk}';

    protected $description = 'Back up the database and private uploads, with checksums';

    public function handle(): int
    {
        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory, 0700);
        $name = 'askonce-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4));
        $work = $directory.'/'.$name;
        File::ensureDirectoryExists($work, 0700);
        $archive = $directory.'/'.$name.'.zip';
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            if ($driver === 'sqlite') {
                $connection->getPdo()->exec('VACUUM INTO '.$connection->getPdo()->quote($work.'/database.sqlite'));
            } elseif ($driver === 'pgsql') {
                $config = $connection->getConfig();
                $process = new Process(['pg_dump', '--format=custom', '--no-owner', '--no-acl', '--file='.$work.'/database.dump', '--host='.$config['host'], '--port='.(string) $config['port'], '--username='.$config['username'], $config['database']], null, ['PGPASSWORD' => $config['password']]);
                $process->setTimeout(600);
                $process->mustRun();
            } else {
                throw new \RuntimeException('Backups support SQLite and PostgreSQL.');
            }
            $zip = new ZipArchive;
            if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new \RuntimeException('Cannot create the backup archive.');
            }
            $checksums = [];
            foreach (File::files($work) as $file) {
                $zip->addFile($file->getPathname(), $file->getFilename());
                $checksums[$file->getFilename()] = hash_file('sha256', $file->getPathname());
            }
            foreach (Storage::disk('local')->allFiles('organizations') as $path) {
                $fullPath = Storage::disk('local')->path($path);
                if (! is_file($fullPath)) {
                    continue;
                }
                $zip->addFile($fullPath, 'uploads/'.$path);
                $checksums['uploads/'.$path] = hash_file('sha256', $fullPath);
            }
            $zip->addFromString('manifest.json', json_encode(['created_at' => now()->toIso8601String(), 'database_driver' => $driver, 'checksums' => $checksums], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $zip->close();
            chmod($archive, 0600);
            $disk = $this->option('offsite-disk') ?: config('app.backup_disk');
            if ($disk) {
                if (! config('filesystems.disks.'.$disk) || in_array($disk, ['local', 'public'], true)) {
                    throw new \RuntimeException('Choose a separately configured private backup disk.');
                }
                $stream = fopen($archive, 'rb');
                try {
                    if (! Storage::disk($disk)->put($name.'.zip', $stream, ['visibility' => 'private'])) {
                        throw new \RuntimeException('Off-site backup failed. The local copy is retained.');
                    }
                } finally {
                    fclose($stream);
                }
            }
            Log::info('backup.created', ['event' => 'backup.created', 'offsite' => (bool) $disk, 'archive_bytes' => filesize($archive)]);
            $this->info('Backup created: '.$archive);
            if (! $disk) {
                $this->warn('Local copy only. Configure a separate off-site destination before beta.');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('backup.failed', ['event' => 'backup.failed', 'exception_type' => $exception::class]);
            $this->error('Backup failed. Check database tools and private backup storage.');

            return self::FAILURE;
        } finally {
            File::deleteDirectory($work);
        }
    }
}
