<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMinimaxFiscalFieldsToInvoicesTable extends Migration
{
    public function up(): void
    {
        Schema::table('minimax_invoices', function (Blueprint $table) {
            $table->string('document_id')->nullable()->after('invoice_date');
            $table->string('document_number')->nullable()->after('document_id');
            $table->string('document_type')->nullable()->after('document_number');
            $table->string('fiscal_attachment_id')->nullable()->after('document_type');
            $table->string('fiscal_file_name')->nullable()->after('fiscal_attachment_id');
        });
    }

    public function down(): void
    {
        Schema::table('minimax_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'document_id',
                'document_number',
                'document_type',
                'fiscal_attachment_id',
                'fiscal_file_name',
           ]);
        });
    }
}
