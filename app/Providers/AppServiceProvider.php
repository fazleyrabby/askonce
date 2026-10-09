<?php

namespace App\Providers;

use App\Support\CurrentOrganization;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentOrganization::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(DiagnosingHealth::class, function (): void {
            DB::select('select 1');
            Cache::get('health:scheduler');
            if (app()->isProduction() && (int) Cache::get('health:scheduler', 0) < now()->subMinutes(10)->timestamp) {
                throw new \RuntimeException('Scheduler heartbeat is stale.');
            }
            if (app()->isProduction() && Cache::get('health:operations') === false) {
                throw new \RuntimeException('Operational health check failed.');
            }
        });
        Event::listen(JobFailed::class, function (JobFailed $event): void {
            Log::error('queue.failed', ['event' => 'queue.failed', 'job' => $event->job->resolveName(), 'exception_type' => $event->exception::class]);
        });
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
        Password::defaults(fn (): Password => $this->app->isProduction() ? Password::min(8)->uncompromised() : Password::min(8));
        RateLimiter::for('login', fn (Request $request): array => [
            Limit::perMinute(5)->by('login-ip:'.$request->ip()),
            Limit::perMinute(10)->by('login-email:'.sha1(Str::lower((string) $request->input('email')))),
        ]);
    }
}
