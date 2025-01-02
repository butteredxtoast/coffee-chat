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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('pronouns')->nullable();
            $table->string('city')->nullable();
            $table->enum('preferred_contact_method', ['slack', 'phone', 'email'])->default('email');
            $table->string('slack_handle')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->unique();
            $table->year('anniversary_year')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
