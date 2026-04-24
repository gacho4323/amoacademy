<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropForeign(['author_id']); // Drop the foreign key constraint
            $table->dropColumn('author_id'); // Drop the author_id column
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->foreignId('author_id')->constrained()->onDelete('cascade')->after('course_id');
        });
    }
};