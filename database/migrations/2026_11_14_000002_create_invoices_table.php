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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('invoice_number')->unique();
            $table->string('invoice_type');      // standard (B2B) | simplified (B2C)
            $table->string('document_type');     // invoice | credit | debit
            $table->date('issue_date');
            $table->string('issue_time', 8);
            $table->string('original_invoice_number')->nullable();
            $table->string('note_reason')->nullable();
            $table->string('payment_means', 2)->default('10');
            $table->string('currency', 3)->default('SAR');

            // Buyer information (required for standard invoices)
            $table->string('buyer_name')->nullable();
            $table->string('buyer_vat_number', 15)->nullable();
            $table->string('buyer_street')->nullable();
            $table->string('buyer_building_number', 4)->nullable();
            $table->string('buyer_district')->nullable();
            $table->string('buyer_city')->nullable();
            $table->string('buyer_postal_code', 5)->nullable();

            $table->decimal('line_extension_amount', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);

            // ZATCA data
            $table->unsignedBigInteger('icv')->nullable();
            $table->string('previous_invoice_hash')->nullable();
            $table->string('invoice_hash')->nullable();
            $table->longText('signed_xml')->nullable();
            $table->text('qr_code')->nullable();
            $table->string('zatca_status')->default('pending');
            $table->json('zatca_response')->nullable();
            $table->longText('cleared_xml')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('quantity', 14, 6);
            $table->decimal('unit_price', 14, 2);
            $table->string('vat_category', 1)->default('S');
            $table->decimal('vat_percent', 5, 2)->default(15);
            $table->decimal('line_extension_amount', 14, 2);
            $table->decimal('vat_amount', 14, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
