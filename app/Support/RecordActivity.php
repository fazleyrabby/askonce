<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class RecordActivity
{
    public function record(string $name, int $organizationId, ?int $requestId = null, ?string $key = null): void
    {
        DB::table('activity_events')->insertOrIgnore(['name' => $name, 'organization_id' => $organizationId, 'client_request_id' => $requestId, 'event_key' => $key, 'created_at' => now()]);
    }
}
