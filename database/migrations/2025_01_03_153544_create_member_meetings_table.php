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
        Schema::create('member_meetings', function (Blueprint $table) {
            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            $table->foreignId('met_with_id')->constrained('members')->onDelete('cascade');
            $table->timestamp('met_at');
            $table->unique(['member_id', 'met_with_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_meetings');
    }
};
