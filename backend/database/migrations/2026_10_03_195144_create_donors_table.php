<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth')->nullable();
            $table->string('phone');
            $table->foreignId('wilaya_id')->constrained();
            $table->foreignId('commune_id')->nullable()->constrained();
            $table->foreignId('blood_type_id')->nullable()->constrained();
            $table->string('availability')->default('temporarily_unavailable');
            $table->boolean('account_verified')->default(false);
            $table->boolean('medical_eligibility_verified')->default(false);
            $table->boolean('consent_to_contact')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donors');
    }
};
