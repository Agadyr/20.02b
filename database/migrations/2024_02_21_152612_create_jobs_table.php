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
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->nullable();
            $table->string('resource_id')->nullable();
            $table->string('image_url')->nullable();
            $table->string('preview_url')->nullable();
            $table->string('image_local_url')->nullable();
            $table->string('preview_local_url')->nullable();
            $table->string('is_final')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
