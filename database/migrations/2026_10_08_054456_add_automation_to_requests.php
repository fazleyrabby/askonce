<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_requests', function (Blueprint $table): void {
            $table->boolean('reminders_paused')->default(false);
            $table->string('reminders_stopped_reason')->nullable();
            $table->unsignedInteger('delivery_generation')->default(0);
            $table->unsignedInteger('progress_revision')->default(0);
            $table->unsignedInteger('notified_revision')->default(0);
            $table->timestamp('progress_notify_at')->nullable()->index();
            $table->timestamp('overdue_notified_at')->nullable();
        });
        Schema::create('reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_request_id')->constrained()->cascadeOnDelete();
            $table->string('delivery_key')->unique();
            $table->string('kind');
            $table->string('status')->default('queued');
            $table->unsignedInteger('generation');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
        Schema::table('client_requests', function (Blueprint $table): void {
            $table->dropColumn(['reminders_paused', 'reminders_stopped_reason', 'delivery_generation', 'progress_revision', 'notified_revision', 'progress_notify_at', 'overdue_notified_at']);
        });
    }
};
