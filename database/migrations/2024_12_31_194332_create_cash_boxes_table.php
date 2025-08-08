<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cash_boxes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // اسم الخزنة
            $table->decimal('balance', 10, 2)->default(0)->nullable();

            // تم استبدال cash_box_type_id بـ payment_method_id
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->onDelete('set null');

            $table->boolean('is_default')->default(1);
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('description')->nullable();
            $table->string('account_number')->nullable();
            $table->unique(['user_id', 'company_id', 'is_default'], 'unique_single_default_cashbox');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_boxes');
    }
};
