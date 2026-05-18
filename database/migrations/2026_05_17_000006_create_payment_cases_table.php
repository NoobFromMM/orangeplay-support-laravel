<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('image_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('provider')->nullable();
            $table->string('transaction_id')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency')->nullable()->default('MMK');
            $table->string('status')->default('pending_review');
            $table->string('customer_email')->nullable();
            $table->json('worker_response')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('conversation_id');
            $table->index('status');
            $table->index('transaction_id');
            $table->index('image_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_cases');
    }
};
