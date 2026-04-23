<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('judge_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('judge_id')->constrained('users')->cascadeOnDelete();
            $table->string('assigned_mode');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'judge_id']);
            $table->index(['match_id', 'checked_in_at']);
            $table->index(['match_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('judge_assignments');
    }
};
