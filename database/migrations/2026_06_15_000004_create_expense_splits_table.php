<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('expense_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->float('raw_value')->nullable(); // original input (%, share weight, or INR for unequal)
            $table->float('share_amount_inr');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_splits');
    }
};
