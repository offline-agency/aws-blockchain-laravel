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
        Schema::create('blockchain_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('version')->default('1.0.0');
            $table->enum('type', ['evm', 'fabric'])->default('evm');
            $table->string('address')->nullable()->index();
            $table->string('network')->index();
            $table->string('deployer_address')->nullable();
            $table->text('abi')->nullable();
            $table->text('bytecode_hash')->nullable();
            $table->json('constructor_params')->nullable();
            $table->timestamp('deployed_at')->nullable();
            $table->string('transaction_hash')->nullable()->index();
            $table->bigInteger('gas_used')->nullable();
            $table->enum('status', ['deployed', 'upgraded', 'deprecated', 'failed'])->default('deployed');
            $table->boolean('is_upgradeable')->default(false);
            $table->unsignedBigInteger('proxy_contract_id')->nullable();
            $table->unsignedBigInteger('implementation_of')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();

            // Add indexes for common queries
            $table->index(['name', 'version']);
            $table->index(['network', 'status']);

            // Foreign key for proxy relationships
            $table->foreign('proxy_contract_id')
                ->references('id')
                ->on('blockchain_contracts')
                ->onDelete('set null');

            $table->foreign('implementation_of')
                ->references('id')
                ->on('blockchain_contracts')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blockchain_contracts');
    }
};
