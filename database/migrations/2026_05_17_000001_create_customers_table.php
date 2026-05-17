<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('platform');
            $table->string('platform_user_id');
            $table->string('display_name')->nullable();
            $table->string('username')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'platform_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
