<?php

namespace App\Actions\Demo;

use App\Actions\Reminders\RequestAutomation;
use App\Actions\Requests\CreateRequest;
use App\Models\Client;
use App\Models\ClientRequest;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\BusinessUpdate;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StartDemoWorkspace
{
    /** Most demo workspaces that may exist at once. */
    public const int MAX_ACTIVE = 100;

    /** Hours a demo workspace is kept before it is purged. */
    public const int LIFETIME_HOURS = 24;

    /** Upload allowance for one demo workspace. */
    public const int STORAGE_QUOTA_BYTES = 10 * 1024 * 1024;

    public function __construct(private CurrentOrganization $context, private CreateRequest $createRequest, private RequestAutomation $automation) {}

    /**
     * Create a private, throwaway workspace with sample clients and requests.
     */
    public function handle(): User
    {
        $previous = $this->context->current();
        try {
            return DB::transaction(function (): User {
                $user = User::create(['name' => 'Demo visitor', 'email' => 'demo-'.Str::lower(Str::random(12)).'@askonce.test', 'password' => Str::random(40)]);
                $user->forceFill(['email_verified_at' => now()])->save();
                $organization = Organization::create(['name' => 'North Studio', 'timezone' => 'UTC']);
                $organization->forceFill(['is_demo' => true, 'storage_quota_bytes' => self::STORAGE_QUOTA_BYTES])->save();
                $organization->users()->attach($user, ['role' => 'owner']);
                $this->context->set($organization);
                $inProgress = null;
                foreach ($this->samples() as $sample) {
                    $client = Client::create($sample['client']);
                    $request = $this->createRequest->handle([
                        'client_id' => $client->id, 'title' => $sample['title'], 'description' => $sample['description'],
                        'due_at' => $sample['due_in_days'] ? now()->addDays($sample['due_in_days'])->toDateString() : null, 'reminder_interval_days' => 3,
                        'items' => array_map(fn (array $item): array => ['label' => $item['label'], 'type' => $item['type'], 'required' => true, 'help_text' => $item['help_text'] ?? null], $sample['items']),
                    ]);
                    foreach ($request->items as $position => $item) {
                        $answer = $sample['items'][$position]['answer'] ?? null;
                        if ($answer !== null) {
                            $item->submission()->create(['value' => $answer]);
                            $item->forceFill(['status' => 'submitted'])->save();
                        }
                    }
                    $this->markStatus($request, $sample['status']);
                    $inProgress = $sample['status'] === 'in_progress' ? $request : $inProgress;
                }
                if ($inProgress) {
                    $user->notify(new BusinessUpdate($organization->id, $inProgress->id, $inProgress->title, 'Your client saved progress.', 'demo-progress:'.$inProgress->id));
                }

                return $user;
            });
        } finally {
            $this->context->restore($previous);
        }
    }

    private function markStatus(ClientRequest $request, string $status): void
    {
        $request->forceFill([
            'status' => $status, 'sent_at' => now(),
            'completed_at' => $status === 'completed' ? now() : null,
            'next_reminder_at' => $status === 'completed' ? null : $this->automation->nextMorning($request),
        ])->save();
    }

    /**
     * @return list<array{client: array{name: string, contact_name: string, email: string}, title: string, description: string, due_in_days: int|null, status: string, items: list<array{label: string, type: string, help_text?: string, answer?: string}>}>
     */
    private function samples(): array
    {
        return [
            [
                'client' => ['name' => 'Oak & Co.', 'contact_name' => 'Sam', 'email' => 'sam@example.test'],
                'title' => 'Tax season documents', 'description' => 'A few details before we start on this year’s return.', 'due_in_days' => null, 'status' => 'completed',
                'items' => [
                    ['label' => 'Registered business name', 'type' => 'text', 'answer' => 'Oak & Co. Carpentry'],
                    ['label' => 'Anything that changed this year', 'type' => 'long_text', 'answer' => 'We hired one part-time employee in March and bought a second van in June.'],
                    ['label' => 'I confirm these details are correct', 'type' => 'confirmation', 'answer' => '1'],
                ],
            ],
            [
                'client' => ['name' => 'Bloom Florist', 'contact_name' => 'Maya', 'email' => 'maya@example.test'],
                'title' => 'Brand photoshoot brief', 'description' => 'Help us plan the shoot. Short answers are fine.', 'due_in_days' => 9, 'status' => 'sent',
                'items' => [
                    ['label' => 'Three words for the mood', 'type' => 'text'],
                    ['label' => 'Reference images', 'type' => 'file', 'help_text' => 'Screenshots or photos you like.'],
                    ['label' => 'Instagram profile', 'type' => 'url'],
                    ['label' => 'Products to feature', 'type' => 'long_text'],
                ],
            ],
            [
                'client' => ['name' => 'ABC Restaurant', 'contact_name' => 'John', 'email' => 'john@example.test'],
                'title' => 'Website content', 'description' => 'Please share the following for your new website. You can save each item and return later.', 'due_in_days' => 4, 'status' => 'in_progress',
                'items' => [
                    ['label' => 'About your business', 'type' => 'long_text', 'answer' => 'Family-run since 1998. Wood-fired cooking, a short seasonal menu, and a room that seats forty.'],
                    ['label' => 'Website URL', 'type' => 'url', 'answer' => 'https://example.test'],
                    ['label' => 'Contact phone number', 'type' => 'text', 'answer' => '555 0100'],
                    ['label' => 'Company logo', 'type' => 'file', 'help_text' => 'A high-resolution PNG or JPG works well.'],
                    ['label' => 'I confirm these details are correct', 'type' => 'confirmation'],
                ],
            ],
        ];
    }
}
