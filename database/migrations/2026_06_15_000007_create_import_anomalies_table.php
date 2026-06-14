<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->integer('row_number');
            $table->json('raw_row')->nullable();
            $table->string('anomaly_type');
            $table->string('severity'); // info|low|high
            $table->text('description');
            $table->text('action_taken');
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->unsignedBigInteger('settlement_id')->nullable();
            $table->boolean('needs_human_review')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_anomalies');
    }
};
