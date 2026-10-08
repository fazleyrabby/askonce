<?php

namespace App\Actions\Submissions;

use App\Actions\Reminders\RequestAutomation;
use App\Models\ClientRequest;
use App\Models\Organization;
use App\Models\Upload;
use App\Support\CurrentOrganization;
use App\Support\RecordActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class SaveItem
{
    public function handle(ClientRequest $request, int $itemId, array $data): void
    {
        $storedPaths = [];
        $replacedPaths = [];
        $quotaReached = false;
        try {
            DB::transaction(function () use ($request, $itemId, $data, &$storedPaths, &$replacedPaths, &$quotaReached): void {
                $expectedTokenHash = $request->token_hash;
                $request = ClientRequest::lockForUpdate()->findOrFail($request->id);
                abort_unless(hash_equals($request->token_hash, $expectedTokenHash), 404);
                abort_unless($request->isEditable(), 409, 'This request is closed.');
                $item = $request->items()->findOrFail($itemId);
                $submission = $item->submission()->firstOrCreate([]);
                if ($item->type === 'file') {
                    $organization = Organization::lockForUpdate()->findOrFail(app(CurrentOrganization::class)->get()->id);
                    $existing = ! empty($data['replace']) ? $submission->uploads()->get() : collect();
                    $size = array_sum(array_map(fn ($file) => $file->getSize(), $data['files']));
                    if (Upload::sum('size') - $existing->sum('size') + $size > $organization->storage_quota_bytes) {
                        $quotaReached = true;
                        throw ValidationException::withMessages(['files' => 'Uploads are unavailable right now — please contact '.$organization->name.'.']);
                    }
                    foreach ($existing as $upload) {
                        $replacedPaths[] = ['disk' => $upload->disk, 'path' => $upload->path];
                        $upload->delete();
                    }
                    foreach ($data['files'] as $file) {
                        $path = $file->store('organizations/'.$organization->id.'/requests/'.$request->id, 'local');
                        if ($path === false) {
                            throw new \RuntimeException('The file could not be saved. Please try again.');
                        }
                        $storedPaths[] = $path;
                        $submission->uploads()->create([
                            'disk' => 'local', 'path' => $path, 'original_name' => basename($file->getClientOriginalName()),
                            'size' => $file->getSize(), 'mime_type' => $file->getMimeType(),
                        ]);
                    }
                    $item->status = 'submitted';
                    $warningPercent = config('app.limits.storage_warning_percent');
                    if (Upload::sum('size') >= $organization->storage_quota_bytes * $warningPercent / 100) {
                        app(RequestAutomation::class)->notify($request, 'storage', "Your storage is over {$warningPercent}% full. Delete requests you no longer need to free space.", 'storage-warning:'.$organization->id.':'.now()->toDateString());
                    }
                } else {
                    $submission->value = $item->type === 'confirmation' ? ($data['value'] ? '1' : null) : ($data['value'] ?? null);
                    $submission->save();
                    $item->status = filled($submission->value) ? 'submitted' : 'pending';
                }
                $item->save();
                $events = app(RecordActivity::class);
                $events->record('client_started', $request->organization_id, $request->id, 'started:'.$request->id.':'.$request->delivery_generation);
                $events->record($item->type === 'file' ? 'file_uploaded' : 'item_submitted', $request->organization_id, $request->id);
                $request->progress_revision++;
                $request->progress_notify_at = now()->addMinutes(30);
                $request->refreshProgress();
            });
            foreach ($replacedPaths as $file) {
                Storage::disk($file['disk'])->delete($file['path']);
            }
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }
            if ($quotaReached) {
                DB::transaction(function () use ($request): void {
                    $locked = ClientRequest::lockForUpdate()->findOrFail($request->id);
                    app(RequestAutomation::class)->notify($locked, 'storage', 'Your storage is full. Client uploads are unavailable.', 'storage-full:'.$locked->organization_id.':'.now()->toDateString());
                });
            }
            throw $exception;
        }
    }
}
