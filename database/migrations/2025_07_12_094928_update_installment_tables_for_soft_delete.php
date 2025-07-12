<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateInstallmentTablesForSoftDelete extends Migration
{
    public function up()
    {
        // 🔁 تعديل جدول installment_plans
        Schema::table('installment_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('installment_plans', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // 🔁 تعديل جدول installments
        Schema::table('installments', function (Blueprint $table) {
            if (!Schema::hasColumn('installments', 'company_id')) {
                $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade')->after('user_id');
            }

            if (!Schema::hasColumn('installments', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('cascade')->after('company_id');
            }

            if (!Schema::hasColumn('installments', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down()
    {
        Schema::table('installment_plans', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('installments', function (Blueprint $table) {
            if (Schema::hasColumn('installments', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }

            if (Schema::hasColumn('installments', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }

            $table->dropSoftDeletes();
        });
    }
}
