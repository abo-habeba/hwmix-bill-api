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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // اسم الشركة
            $table->text('description'); // نبذة مختصرة
            $table->string('field'); // المجال أو التخصص
            $table->string('owner_name'); // اسم صاحب الشركة
            $table->string('address'); // عنوان الشركة
            $table->string('phone'); // رقم الهاتف
            $table->string('email')->unique(); // البريد الإلكتروني
            $table->timestamps(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
