<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_occurrences', function (Blueprint $table) {
            $table->id();
            $table->string('protocol', 10)->unique();
            $table->string('error_code', 40)->index();
            $table->unsignedSmallInteger('http_status')->index();
            $table->string('status', 24)->index();
            $table->boolean('security_related')->default(false)->index();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->index();
            $table->string('route', 160)->nullable();
            $table->string('method', 10);
            $table->string('path', 500);
            $table->string('exception_class', 255)->nullable();
            $table->string('ip_hash', 64)->nullable()->index();
            $table->text('internal_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('retention_until')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_occurrences');
    }
};
