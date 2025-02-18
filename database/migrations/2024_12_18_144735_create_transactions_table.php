<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('target_user_id')->nullable();
            $table->string('cashbox_id')->nullable();
            $table->string('target_cashbox_id')->nullable();
            $table->string('original_transaction_id')->nullable();
            $table->string('type');
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 10, 2)->default(0);
            $table->decimal('balance_after', 10, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('set null');
            $table->foreign('target_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
