<?php

namespace App\Http\Controllers;

use App\Actions\Reminders\RequestAutomation;
use App\Models\ClientRequest;
use App\Models\Upload;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', ['organization' => app(CurrentOrganization::class)->get(), 'usedBytes' => Upload::sum('size')]);
    }

    public function update(Request $request, RequestAutomation $automation): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'timezone' => ['required', 'timezone']]);
        DB::transaction(function () use ($data, $automation): void {
            $organization = app(CurrentOrganization::class)->get();
            $changed = $organization->timezone !== $data['timezone'];
            $organization->update($data);
            if ($changed) {
                ClientRequest::whereIn('status', ['sent', 'in_progress'])->where('reminders_paused', false)->whereNull('reminders_stopped_reason')->each(function (ClientRequest $request) use ($automation): void {
                    $request = ClientRequest::lockForUpdate()->findOrFail($request->id);
                    $request->next_reminder_at = $automation->nextMorning($request);
                    $request->save();
                });
            }
        });

        return back()->with('status', 'Business settings saved.');
    }
}
