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
        Schema::table('student_exam_sessions', function (Blueprint $table) {
            $table->unsignedInteger('total_answered')->default(0)->after('correct_answers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_exam_sessions', function (Blueprint $table) {
            $table->dropColumn('total_answered');
        });
    }
};
