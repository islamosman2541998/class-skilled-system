<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per (type, record, key) already notified, so a periodic check never repeats itself.
        Schema::create('notification_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('dedupe_key', 100);
            $table->timestamps();

            $table->unique(['type', 'subject_type', 'subject_id', 'dedupe_key'], 'notification_dispatches_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_dispatches');
    }
};
