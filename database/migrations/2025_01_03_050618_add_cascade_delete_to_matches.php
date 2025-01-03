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
        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['member1_id']);
            $table->dropForeign(['member2_id']);

            $table->foreign('member1_id')
                ->references('id')
                ->on('members')
                ->onDelete('cascade');

            $table->foreign('member2_id')
                ->references('id')
                ->on('members')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['member1_id']);
            $table->dropForeign(['member2_id']);

            $table->foreign('member1_id')
                ->references('id')
                ->on('members');

            $table->foreign('member2_id')
                ->references('id')
                ->on('members');
        });
    }
};
