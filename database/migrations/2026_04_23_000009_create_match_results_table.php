<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->string('winner_side');
            $table->unsignedTinyInteger('winner_vote_count');
            $table->unsignedTinyInteger('loser_vote_count');
            $table->decimal('official_margin', 6, 1);
            $table->decimal('official_team_score_government', 6, 1);
            $table->decimal('official_team_score_opposition', 6, 1);
            $table->foreignId('best_speaker_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->boolean('is_force_completed')->default(false);
            $table->boolean('is_provisional')->default(false);
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique('match_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_results');
    }
};
