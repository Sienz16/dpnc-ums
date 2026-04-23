<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rounds', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sequence')->nullable();
            $table->timestamps();

            $table->unique('sequence');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rounds');
    }
};
