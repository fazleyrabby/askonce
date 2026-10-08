<?php

namespace App\Console\Commands;

use App\Actions\Demo\StartDemoWorkspace;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurgeDemoWorkspaces extends Command
{
    protected $signature = 'askonce:purge-demos';

    protected $description = 'Delete expired demo workspaces, their accounts and uploads';

    public function handle(): int
    {
        $purged = 0;
        Organization::where('is_demo', true)->where('created_at', '<', now()->subHours(StartDemoWorkspace::LIFETIME_HOURS))->each(function (Organization $organization) use (&$purged): void {
            $uploads = DB::table('uploads')->where('organization_id', $organization->id)->get(['disk', 'path']);
            DB::transaction(function () use ($organization): void {
                $userIds = $organization->users()->pluck('users.id');
                /** Requests restrict client deletion, so they must go before the organization cascade. */
                DB::table('client_requests')->where('organization_id', $organization->id)->delete();
                DB::table('notifications')->where('notifiable_type', (new User)->getMorphClass())->whereIn('notifiable_id', $userIds)->delete();
                DB::table('sessions')->whereIn('user_id', $userIds)->delete();
                $organization->delete();
                DB::table('users')->whereIn('id', $userIds)->delete();
            });
            foreach ($uploads as $upload) {
                Storage::disk($upload->disk)->delete($upload->path);
            }
            $purged++;
        });
        $this->info("Purged {$purged} demo workspaces.");

        return self::SUCCESS;
    }
}
