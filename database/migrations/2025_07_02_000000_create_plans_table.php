<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // اسم الخطة
            $table->string('code')->nullable(); // كود فريد للخطة
            $table->text('description')->nullable(); // وصف الخطة
            $table->unsignedBigInteger('company_id')->nullable(); // الشركة المالكة للخطة (إن وجدت)
            $table->decimal('price', 15, 2)->default(0); // سعر الخطة
            $table->string('currency', 10)->nullable(); // العملة
            $table->integer('duration')->nullable(); // مدة الخطة
            $table->string('duration_unit', 20)->nullable(); // وحدة المدة (يوم/شهر/سنة)
            $table->integer('trial_days')->nullable(); // عدد أيام التجربة المجانية
            $table->boolean('is_active')->default(true); // حالة التفعيل
            $table->json('features')->nullable(); // مزايا الخطة (JSON)
            $table->integer('max_users')->nullable(); // الحد الأقصى للمستخدمين
            $table->integer('max_projects')->nullable(); // الحد الأقصى للمشاريع
            $table->integer('max_storage_mb')->nullable(); // الحد الأقصى للتخزين بالميجابايت
            $table->string('type')->nullable(); // نوع الخطة (شهري/سنوي/مخصص)
            $table->string('icon')->nullable(); // أيقونة أو صورة للخطة
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
