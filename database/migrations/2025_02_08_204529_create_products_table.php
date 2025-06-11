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
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->string('name');  // اسم المنتج
            $table->string('slug')->unique();  // اسم URL-friendly

            $table->boolean('active')->default(true);  // هل المنتج مفعل
            $table->boolean('featured')->default(false);  // منتج مميز
            $table->boolean('returnable')->default(true);  // هل يقبل الاسترجاع

            $table->text('desc')->nullable();  // وصف قصير
            $table->text('desc_long')->nullable();  // وصف تفصيلي
            $table->timestamp('published_at')->nullable();  // تاريخ النشر

            $table->foreignId('category_id')->constrained()->onDelete('cascade');  // التصنيف
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();  // الماركة

            $table->foreignId('company_id')->constrained()->onDelete('cascade');  // الشركة المالكة
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');  // أنشئ بواسطة

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
