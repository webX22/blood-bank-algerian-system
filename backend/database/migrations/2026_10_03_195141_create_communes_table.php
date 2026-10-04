<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wilaya_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('name_ar');
            $table->string('name_fr');
            $table->string('name_en');
            $table->string('slug')->nullable();
            $table->timestamps();

            $table->unique(['wilaya_id', 'code']);
            $table->index('wilaya_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communes');
    }
};
