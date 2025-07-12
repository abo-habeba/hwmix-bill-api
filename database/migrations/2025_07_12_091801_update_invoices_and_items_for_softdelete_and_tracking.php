<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ✅ جدول الفواتير
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->after('created_by');
            }

            if (!Schema::hasColumn('invoices', 'deleted_at')) {
                $table->softDeletes();
            }

            // تحديث نوع الحقل لو كان status مش enum
            $table->enum('status', ['draft', 'confirmed', 'canceled'])->default('confirmed')->change();
        });

        // ✅ جدول بنود الفاتورة
        Schema::table('invoice_items', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_items', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('updated_by');
            $table->dropSoftDeletes();
            $table->string('status')->change(); // أو رجعها حسب النوع الأصلي
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
