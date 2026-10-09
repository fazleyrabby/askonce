<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CheckHealth extends Command
{
    protected $signature = 'askonce:health {--heartbeat : Record that the scheduler ran}';

    protected $description = 'Check database, cache, storage, queue failures and backup freshness';

    public function handle(): int
    {
        $checks = [];
        foreach ([
            'database' => fn (): bool => DB::select('select 1') !== [],
            'cache' => function (): bool {
                $key = 'health:probe:'.bin2hex(random_bytes(8));
                Cache::put($key, 'ok', 30);
                $healthy = Cache::get($key) === 'ok';
                Cache::forget($key);

                return $healthy;
            },
            'storage' => function (): bool {
                $path = 'health/'.bin2hex(random_bytes(8));
                try {
                    return Storage::disk('local')->put($path, 'ok') && Storage::disk('local')->get($path) === 'ok';
                } finally {
                    Storage::disk('local')->delete($path);
                }
            },
            'queue' => fn (): bool => DB::table('failed_jobs')->count() === 0,
            'backup' => fn (): bool => ! config('app.backups_enabled') || collect(File::glob(storage_path('app/backups/*.zip')))->contains(fn (string $path): bool => filemtime($path) > now()->subHours(26)->timestamp),
        ] as $name => $probe) {
            try {
                $checks[$name] = $probe();
            } catch (Throwable) {
                $checks[$name] = false;
            }
        }
        if ($this->option('heartbeat') && $checks['cache']) {
            Cache::put('health:scheduler', now()->timestamp, 600);
        }
        $healthy = ! in_array(false, $checks, true);
        if ($checks['cache']) {
            Cache::put('health:operations', $healthy, 900);
        }
        Log::log($healthy ? 'info' : 'error', 'operations.health', ['event' => 'operations.health', ...$checks]);
        $this->line(json_encode($checks, JSON_THROW_ON_ERROR));

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
