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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->string('invoice_number')->unique()->nullable();
            $table->date('due_date')->nullable();

            $table->decimal('gross_amount', 15, 2);
            $table->decimal('total_discount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);

            $table->enum('status', ['draft', 'confirmed', 'canceled'])->default('confirmed');
            $table->text('notes')->nullable();

            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('invoice_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('cash_box_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_type_code')->nullable();

            $table->integer('round_step')->nullable();

            $table->timestamps();
            $table->softDeletes(); // ✅ مهم جدًا
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
