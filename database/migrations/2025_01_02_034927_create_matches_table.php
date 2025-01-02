<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member1_id')->constrained('members');
            $table->foreignId('member2_id')->constrained('members');
            $table->boolean('met')->default(false);
            $table->boolean('is_current')->default(true);
            $table->timestamp('matched_at');
            $table->timestamp('met_confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['member1_id', 'member2_id', 'is_current']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
