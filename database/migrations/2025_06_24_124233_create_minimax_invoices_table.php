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
        Schema::create('minimax_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_id')->unique(); // ID iz Minimax API
            $table->string('customer_name');
            $table->string('file_name');
            $table->string('storage_path');
            $table->decimal('total_amount', 10, 2);
            $table->date('invoice_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minimax_invoices');
    }
};
