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
        Schema::create('blood_donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donor_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained('healthcare_facilities')->restrictOnDelete();
            $table->foreignId('blood_type_id')->constrained();
            $table->foreignId('blood_component_id')->constrained('blood_components');
            $table->foreignId('blood_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('bag_code')->nullable()->unique();
            $table->unsignedInteger('volume_ml')->nullable();
            $table->timestamp('donated_at');
            $table->date('expires_at')->nullable();
            $table->string('status')->default('collected');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['facility_id', 'status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blood_donations');
    }
};
