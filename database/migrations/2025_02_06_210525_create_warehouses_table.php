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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();

            $table->string('name');  // اسم المخزن
            $table->string('location')->nullable();  // موقع المخزن
            $table->string('manager')->nullable();  // اسم المسؤول
            $table->integer('capacity')->nullable();  // السعة

            $table->enum('status', ['active', 'inactive'])->default('active');  // حالة المخزن

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
        Schema::dropIfExists('warehouses');
    }
};
