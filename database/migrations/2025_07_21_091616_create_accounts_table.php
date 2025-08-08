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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // اسم الحساب (مثل: نقدية، مبيعات، إيجار)
            $table->string('code')->unique(); // رمز فريد للحساب (مثل: 100، 400، 500)
            $table->string('type'); // نوع الحساب (مثل: Asset, Liability, Equity, Revenue, Expense)
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->onDelete('cascade'); // للحسابات الفرعية
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade'); // ربط الحساب بالشركة
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
