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

    // العلاقات الأساسية
    $table->foreignId('company_id')->constrained()->onDelete('cascade');
    $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // من أنشأ الفاتورة
    $table->foreignId('user_id')->constrained()->onDelete('cascade'); // العميل
    $table->foreignId('invoice_type_id')->constrained()->onDelete('cascade');

    // البيانات الأساسية
    $table->string('invoice_number')->unique()->nullable();
    $table->date('due_date')->nullable();
    $table->decimal('total_amount', 15, 2);
    $table->string('status'); // draft, confirmed
    $table->text('notes')->nullable();

    $table->timestamps();
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
