<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// جدول الأقساط
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_plan_id')->constrained('installment_plans')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');  // << أضفت العلاقة دي
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');  // << أضفت العلاقة دي  created_by
            $table->string('installment_number')->nullable();
            $table->date('due_date');
            $table->decimal('amount', 15, 2);
            $table->string('status');
            $table->timestamp('paid_at')->nullable();
            $table->decimal('remaining', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
