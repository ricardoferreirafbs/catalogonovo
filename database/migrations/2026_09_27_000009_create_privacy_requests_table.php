<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_requests', function (Blueprint $table) {
            $table->id();
            $table->string('protocol', 15)->unique();
            $table->string('request_type', 40)->index();
            $table->string('scope', 20)->index();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->text('requester_name');
            $table->text('requester_email');
            $table->string('requester_email_hash', 64)->index();
            $table->text('company_reference')->nullable();
            $table->text('details')->nullable();
            $table->string('status', 24)->index();
            $table->timestamp('email_verified_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->index();
            $table->text('internal_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('retention_until')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');
    }
};
