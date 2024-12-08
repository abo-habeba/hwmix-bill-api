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
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('locale'); // اللغة (ar, en, ...)
            $table->string('field'); // اسم الحقل المراد ترجمته (مثل title, description)
            $table->text('value'); // القيمة المترجمة
            $table->morphs('model'); // لعلاقة polymorphic (model_id, model_type)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
