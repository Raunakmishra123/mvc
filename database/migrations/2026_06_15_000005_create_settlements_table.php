<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('paid_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('paid_to')->constrained('users')->cascadeOnDelete();
            $table->float('amount_inr');
            $table->date('settlement_date');
            $table->text('notes')->nullable();
            $table->string('source')->default('manual');
            $table->unsignedBigInteger('import_batch_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
