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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->integer('step')->default(0);
            $table->string('phone')->nullable();
            $table->string('second_phone')->nullable();
            $table->string('username')->nullable();
            $table->integer('certificate')->nullable();
            $table->string('regions')->nullable();
            $table->string('districts')->nullable();
            $table->string('schools')->nullable();
            $table->unsignedBigInteger('telegram_id')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
