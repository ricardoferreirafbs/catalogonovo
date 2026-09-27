<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('error_occurrences', function (Blueprint $table) {
            $table->boolean('personal_data_incident')->default(false)->index()->after('security_related');
            $table->string('risk_assessment', 32)->nullable()->after('personal_data_incident');
            $table->unsignedInteger('affected_subjects_estimate')->nullable()->after('risk_assessment');
            $table->text('affected_data_categories')->nullable()->after('affected_subjects_estimate');
            $table->text('containment_measures')->nullable()->after('affected_data_categories');
            $table->timestamp('incident_confirmed_at')->nullable()->after('containment_measures');
            $table->timestamp('anpd_notified_at')->nullable()->after('incident_confirmed_at');
            $table->timestamp('data_subjects_notified_at')->nullable()->after('anpd_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('error_occurrences', function (Blueprint $table) {
            $table->dropColumn([
                'personal_data_incident', 'risk_assessment', 'affected_subjects_estimate',
                'affected_data_categories', 'containment_measures', 'incident_confirmed_at',
                'anpd_notified_at', 'data_subjects_notified_at',
            ]);
        });
    }
};
