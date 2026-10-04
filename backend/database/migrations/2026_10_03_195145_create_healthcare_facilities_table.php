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
        Schema::create('healthcare_facilities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code')->nullable()->unique();
            $table->string('facility_type')->default('hospital');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->foreignId('wilaya_id')->constrained();
            $table->foreignId('commune_id')->nullable()->constrained();
            $table->string('address')->nullable();
            $table->boolean('verified')->default(false);
            $table->boolean('accepts_requests')->default(false);
            $table->timestamps();

            $table->index(['wilaya_id', 'verified']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('healthcare_facilities');
    }
};
