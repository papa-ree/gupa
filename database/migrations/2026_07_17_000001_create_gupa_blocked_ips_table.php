<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gupa_blocked_ips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ip', 45);
            $table->string('reason')->default('score threshold exceeded');
            $table->boolean('is_permanent')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('ip');
            $table->index('is_permanent');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gupa_blocked_ips');
    }
};
