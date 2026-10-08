<?php

namespace App\Actions\Requests;

use App\Models\Client;
use App\Models\ClientRequest;
use App\Support\RecordActivity;
use Illuminate\Support\Facades\DB;

class CreateRequest
{
    public function handle(array $data): ClientRequest
    {
        return DB::transaction(function () use ($data): ClientRequest {
            Client::findOrFail($data['client_id']);
            $request = new ClientRequest(collect($data)->except('items')->all());
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $request->token = $token;
            $request->token_hash = hash('sha256', $token);
            $request->save();
            foreach ($data['items'] as $position => $item) {
                $request->items()->create([...$item, 'position' => $position]);
            }

            app(RecordActivity::class)->record('request_created', $request->organization_id, $request->id);

            return $request;
        });
    }
}
