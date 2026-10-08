<?php

namespace App\Models;

use App\Actions\Reminders\RequestAutomation;
use App\Models\Concerns\BelongsToOrganization;
use App\Support\CurrentOrganization;
use App\Support\RecordActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ClientRequest extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = ['client_id', 'title', 'description', 'due_at', 'reminder_interval_days'];

    protected $attributes = ['delivery_generation' => 0, 'reminders_sent' => 0, 'progress_revision' => 0, 'notified_revision' => 0, 'reminders_paused' => false];

    protected $hidden = ['token', 'token_hash'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'due_at' => 'date', 'sent_at' => 'datetime', 'completed_at' => 'datetime', 'next_reminder_at' => 'datetime', 'progress_notify_at' => 'datetime', 'reminders_paused' => 'boolean'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequestItem::class)->orderBy('position');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['sent', 'in_progress'], true);
    }

    public function isOverdue(): bool
    {
        return $this->isEditable() && $this->due_at && now()->greaterThan(Carbon::parse($this->due_at->format('Y-m-d'), app(CurrentOrganization::class)->get()->timezone)->endOfDay());
    }

    public function publicUrl(): string
    {
        return route('public-request.show', ['token' => $this->token]);
    }

    public function refreshProgress(): void
    {
        if (! $this->isEditable()) {
            return;
        }
        if (! $this->items()->where('required', true)->where('status', 'pending')->exists()) {
            $this->status = 'completed';
            app(RecordActivity::class)->record('request_completed', $this->organization_id, $this->id, 'completed:'.$this->id.':'.$this->delivery_generation);
            $this->completed_at = now();
            $this->next_reminder_at = null;
            $this->progress_notify_at = null;
            $this->notified_revision = $this->progress_revision;
            app(RequestAutomation::class)->notify($this, 'completed', 'Your request is complete.', 'completed:'.$this->id.':'.$this->delivery_generation);
        } elseif ($this->items()->where('status', 'submitted')->exists()) {
            $this->status = 'in_progress';
        }
        $this->save();
    }
}
