<?php

namespace App\Actions\Requests;

use App\Models\ClientRequest;
use App\Models\Submission;
use App\Models\Upload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteRequest
{
    public function handle(ClientRequest $clientRequest): void
    {
        $paths = [];
        DB::transaction(function () use ($clientRequest, &$paths): void {
            $request = ClientRequest::lockForUpdate()->findOrFail($clientRequest->id);
            $submissions = Submission::whereIn('request_item_id', $request->items()->pluck('id'))->pluck('id');
            $paths = Upload::whereIn('submission_id', $submissions)->get(['disk', 'path'])->toArray();
            $request->delete();
        });
        foreach ($paths as $path) {
            Storage::disk($path['disk'])->delete($path['path']);
        }

    }
}
