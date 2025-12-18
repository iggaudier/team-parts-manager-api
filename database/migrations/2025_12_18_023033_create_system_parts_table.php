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
        Schema::create('system_parts', function (Blueprint $table) {
            $table->id();
            $table->string('part_type');
            $table->string('manufacturer');
            $table->string('model_number');
            $table->decimal('list_price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Unique constraint for manufacturer + model_number
            $table->unique(['manufacturer', 'model_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_parts');
    }
};
