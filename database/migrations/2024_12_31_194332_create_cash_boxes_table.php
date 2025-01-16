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
            $table->string('name');
            $table->decimal('balance', 10, 2)->default(0);
            $table->string('cash_type');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->unique(['company_id', 'cash_type'], 'unique_cash_type_per_company');
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
