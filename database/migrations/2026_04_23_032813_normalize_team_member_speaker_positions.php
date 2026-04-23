<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('team_members')->update([
            'speaker_position' => DB::raw("
                CASE speaker_position
                    WHEN 'PM' THEN 'speaker_1'
                    WHEN 'KP' THEN 'speaker_1'
                    WHEN 'TPM' THEN 'speaker_2'
                    WHEN 'TKP' THEN 'speaker_2'
                    WHEN 'M1' THEN 'speaker_3'
                    WHEN 'P1' THEN 'speaker_3'
                    ELSE speaker_position
                END
            "),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible: previous values mixed speaker order with match side.
    }
};
