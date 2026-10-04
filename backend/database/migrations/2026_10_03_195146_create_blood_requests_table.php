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
        Schema::create('blood_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained('healthcare_facilities')->nullOnDelete();
            $table->foreignId('blood_type_id')->constrained();
            $table->foreignId('blood_component_id')->constrained('blood_components');
            $table->foreignId('wilaya_id')->constrained();
            $table->foreignId('commune_id')->nullable()->constrained();
            $table->unsignedSmallInteger('units')->default(1);
            $table->unsignedSmallInteger('fulfilled_units')->default(0);
            $table->string('urgency')->default('routine');
            $table->string('status')->default('open');
            $table->timestamp('needed_by');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'urgency', 'needed_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blood_requests');
    }
};
