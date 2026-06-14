<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('description');
            $table->date('expense_date');
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('split_type'); // equal|unequal|percentage|share
            $table->float('original_amount');
            $table->string('original_currency', 3)->default('INR');
            $table->float('exchange_rate')->default(1.0);
            $table->float('amount_inr');
            $table->text('notes')->nullable();
            $table->boolean('needs_review')->default(false);
            $table->text('review_reason')->nullable();
            $table->foreignId('is_duplicate_of')->nullable()->constrained('expenses')->nullOnDelete();
            $table->boolean('excluded_from_balances')->default(false);
            $table->string('source')->default('manual'); // manual|import
            $table->foreignId('import_batch_id')->nullable(); // no FK constraint since table created later
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
