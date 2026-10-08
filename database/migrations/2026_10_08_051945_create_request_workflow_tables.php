<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->date('due_at')->nullable();
            $table->unsignedSmallInteger('reminder_interval_days')->default(3);
            $table->timestamp('next_reminder_at')->nullable()->index();
            $table->unsignedSmallInteger('reminders_sent')->default(0);
            $table->string('token_hash', 64)->unique();
            $table->text('token');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_submitted_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });
        Schema::create('request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_request_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->text('help_text')->nullable();
            $table->string('type');
            $table->boolean('required')->default(true);
            $table->unsignedInteger('position');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->unsignedBigInteger('size');
            $table->string('mime_type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['uploads', 'submissions', 'request_items', 'client_requests'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
