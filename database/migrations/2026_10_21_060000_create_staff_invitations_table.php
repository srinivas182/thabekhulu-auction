<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            // SHA-256 of the emailed token; the plain token is never stored.
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_invitations');
    }
};
