<?php

namespace App\Console\Commands;

use App\Actions\Reminders\RequestAutomation;
use App\Models\ClientRequest;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessRequestAutomation extends Command
{
    protected $signature = 'requests:automate';

    protected $description = 'Queue due reminders and batched business updates';

    public function handle(RequestAutomation $automation): int
    {
        $context = app(CurrentOrganization::class);
        $previous = $context->current();
        try {
            Organization::each(function (Organization $organization) use ($context, $automation): void {
                $context->set($organization);
                ClientRequest::whereIn('status', ['sent', 'in_progress', 'completed'])->each(function (ClientRequest $request) use ($automation): void {
                    DB::transaction(function () use ($request, $automation): void {
                        $request = ClientRequest::lockForUpdate()->find($request->id);
                        if (! $request) {
                            return;
                        }
                        $stale = DB::table('reminders')->where('client_request_id', $request->id)->where('generation', $request->delivery_generation)->where('status', 'sending')->where('claimed_at', '<', now()->subMinutes(10))->get();
                        foreach ($stale as $delivery) {
                            DB::table('reminders')->where('id', $delivery->id)->update(['status' => 'uncertain', 'updated_at' => now()]);
                            if (! str_starts_with($delivery->kind, 'business:')) {
                                $automation->stop($request, 'delivery_uncertain');
                            }
                        }
                        $automation->process($request);
                        DB::table('reminders')->where('client_request_id', $request->id)->where('status', 'queued')->orderBy('id')->each(function (object $delivery) use ($request, $automation): void {
                            $automation->queue($request, $delivery->kind, $delivery->delivery_key);
                        });
                    });
                });
            });
        } finally {
            $context->restore($previous);
        }

        return self::SUCCESS;
    }
}
