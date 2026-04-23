<?php

use App\Domain\Debate\Enums\ScoreSheetState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_sheets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('judge_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('mark_pm', 5, 1)->default(0);
            $table->decimal('mark_tpm', 5, 1)->default(0);
            $table->decimal('mark_m1', 5, 1)->default(0);
            $table->decimal('mark_kp', 5, 1)->default(0);
            $table->decimal('mark_tkp', 5, 1)->default(0);
            $table->decimal('mark_p1', 5, 1)->default(0);
            $table->decimal('mark_penggulungan_gov', 5, 1)->default(0);
            $table->decimal('mark_penggulungan_opp', 5, 1)->default(0);
            $table->decimal('gov_total', 6, 1)->default(0);
            $table->decimal('opp_total', 6, 1)->default(0);
            $table->decimal('margin', 6, 1)->default(0);
            $table->string('winner_side')->nullable();
            $table->foreignId('best_debater_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->string('state')->default(ScoreSheetState::Draft->value);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'judge_id']);
            $table->index(['match_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_sheets');
    }
};
