<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('healthcare_facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['healthcare_facility_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_staff');
    }
};
