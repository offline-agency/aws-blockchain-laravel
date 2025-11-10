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
        Schema::create('blockchain_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_hash')->unique()->index();
            $table->unsignedBigInteger('contract_id');
            $table->string('method_name');
            $table->json('parameters')->nullable();
            $table->json('return_values')->nullable();
            $table->bigInteger('gas_used')->nullable();
            $table->bigInteger('gas_price')->nullable();
            $table->string('from_address')->nullable();
            $table->string('to_address')->nullable();
            $table->enum('status', ['pending', 'success', 'failed', 'reverted'])->default('pending');
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('rollback_id')->nullable();
            $table->unsignedBigInteger('block_number')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            // Add indexes for common queries
            $table->index(['contract_id', 'method_name']);
            $table->index(['status', 'created_at']);
            $table->index('block_number');
            
            // Foreign keys
            $table->foreign('contract_id')
                ->references('id')
                ->on('blockchain_contracts')
                ->onDelete('cascade');
                
            $table->foreign('rollback_id')
                ->references('id')
                ->on('blockchain_transactions')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blockchain_transactions');
    }
};

