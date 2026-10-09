<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class OperationalSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_logs_exclude_credentials_tokens_and_exception_messages(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'askonce-log-');
        config(['logging.channels.production.handler_with.stream' => $path]);
        try {
            Log::channel('production')->error('secret-client-token', [
                'event' => 'queue.failed', 'job' => 'App\\Jobs\\DeliverRequestMail',
                'email' => 'private@example.test', 'password' => 'secret-password',
                'exception' => new RuntimeException('SMTP password=secret-password'),
                'url' => '/r/secret-client-token', 'request_id' => 'safe-id',
            ]);
            $record = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('queue.failed', $record['message']);
            $this->assertSame(RuntimeException::class, $record['context']['exception_type']);
            $this->assertSame('safe-id', $record['context']['request_id']);
            $this->assertStringNotContainsString('secret-', file_get_contents($path));
            $this->assertStringNotContainsString('private@example', file_get_contents($path));
        } finally {
            Log::forgetChannel('production');
            unlink($path);
        }
    }

    public function test_request_logs_use_route_names_and_server_generated_ids(): void
    {
        config(['app.telemetry_enabled' => true]);
        Log::spy();
        $response = $this->withHeaders(['X-Request-ID' => 'untrusted'])->get('/login?password=secret');
        $response->assertOk()->assertHeader('X-Request-ID');
        $this->assertNotSame('untrusted', $response->headers->get('X-Request-ID'));
        $this->get('/missing-audit-route')->assertNotFound()->assertHeader('X-Request-ID');
        Log::shouldHaveReceived('log')->withArgs(fn (string $level, string $message, array $context): bool => $context['status'] === 404);
        Log::shouldHaveReceived('log')->withArgs(fn (string $level, string $message, array $context): bool => $message === 'http.request' && $context['route'] === 'login' && $context['status'] === 200 && ! str_contains(json_encode($context), 'secret'));
    }

    public function test_health_command_checks_services_and_records_scheduler_heartbeat(): void
    {
        Storage::fake('local');
        config(['app.backups_enabled' => false]);
        $this->artisan('askonce:health --heartbeat')->assertSuccessful();
        $this->assertSame(now()->timestamp, Cache::get('health:scheduler'));
        $this->assertSame([], Storage::disk('local')->allFiles('health'));
    }

    public function test_health_check_fails_for_stale_backups_and_scheduler(): void
    {
        config(['app.backups_enabled' => true]);
        $original = storage_path();
        $this->app->useStoragePath(sys_get_temp_dir().'/askonce-empty-'.bin2hex(random_bytes(8)));
        try {
            $this->artisan('askonce:health')->assertFailed();
        } finally {
            $this->app->useStoragePath($original);
        }
        $this->app['env'] = 'production';
        Cache::forget('health:scheduler');
        $this->get('/up')->assertStatus(500);
        Cache::put('health:scheduler', now()->timestamp, 600);
        Cache::put('health:operations', false, 900);
        $this->get('/up')->assertStatus(500);
        Cache::put('health:operations', true, 900);
        $this->get('/up')->assertOk();
    }
}
