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
        Schema::create('user_company_cash', function (Blueprint $table) {
            Schema::create('user_company_cash', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
                $table->foreignId('cash_box_id')->constrained('cash_boxes')->onDelete('cascade');
                $table->unique(['user_id', 'company_id', 'cash_box_id']);
                $table->timestamps();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_company_cash');
    }
};
