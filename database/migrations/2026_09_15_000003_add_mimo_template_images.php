<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('hero_image_2_path')->nullable()->after('hero_image_path');
            $table->string('hero_image_3_path')->nullable()->after('hero_image_2_path');
            $table->string('experience_image_path')->nullable()->after('hero_image_3_path');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['hero_image_2_path', 'hero_image_3_path', 'experience_image_path']);
        });
    }
};
