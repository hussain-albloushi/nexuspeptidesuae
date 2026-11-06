<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->string('type')->default('text')->after('label');
            $table->json('data')->nullable()->after('body');
            $table->integer('sort_order')->default(0)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->dropColumn(['type', 'data', 'sort_order']);
        });
    }
};
