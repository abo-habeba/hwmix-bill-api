<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('profits', function (Blueprint $table) {
            $table->id();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('company_id');
            $table->decimal('revenue_amount', 15, 2);
            $table->decimal('cost_amount', 15, 2);
            $table->decimal('profit_amount', 15, 2);
            $table->string('note')->nullable();
            $table->date('profit_date');
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('profits');
    }
};
