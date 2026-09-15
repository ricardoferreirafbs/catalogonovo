<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->json('content')->nullable()->after('theme');
            $table->string('hero_image_path')->nullable()->after('logo_path');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('tenant_id')->constrained('categories')->nullOnDelete();
            $table->text('description')->nullable()->after('slug');
            $table->boolean('show_in_menu')->default(false)->after('is_active');
            $table->index(['tenant_id', 'parent_id', 'sort_order']);
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('url');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('open_new_tab')->default(false);
            $table->timestamps();
            $table->index(['tenant_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['tenant_id', 'parent_id', 'sort_order']);
            $table->dropColumn(['parent_id', 'description', 'show_in_menu']);
        });
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['content', 'hero_image_path']);
        });
    }
};
