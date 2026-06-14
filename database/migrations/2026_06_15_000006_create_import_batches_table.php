<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('filename');
            $table->foreignId('imported_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->integer('row_count')->default(0);
            $table->integer('anomaly_count')->default(0);
            $table->string('status')->default('processing'); // processing|done|failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
