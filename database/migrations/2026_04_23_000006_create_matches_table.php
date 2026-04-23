<?php

use App\Domain\Debate\Enums\MatchStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('government_team_id')->constrained('teams')->restrictOnDelete();
            $table->foreignId('opposition_team_id')->constrained('teams')->restrictOnDelete();
            $table->unsignedTinyInteger('judge_panel_size');
            $table->string('status')->default(MatchStatus::Pending->value);
            $table->string('completion_type')->nullable();
            $table->string('result_state')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['round_id', 'room_id']);
            $table->unique(['round_id', 'room_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
