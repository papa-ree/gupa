<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gupa_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ip', 45);
            $table->string('event');
            $table->string('reason');
            $table->integer('score')->default(0);
            $table->string('path')->nullable();
            $table->string('method', 10)->nullable();
            $table->string('user_agent')->nullable();
            $table->smallInteger('status_code')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('ip');
            $table->index('event');
            $table->index('status_code');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gupa_logs');
    }
};
