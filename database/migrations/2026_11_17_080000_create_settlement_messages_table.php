<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Settlement conversation: staff questions, buyer replies, uploads and decisions, kept with the sale record. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_case_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('settlement_upload_id')->nullable();   // the upload a question is about, if any
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author', 10);                                   // staff | buyer
            $table->string('kind', 20);                                     // question | reply | upload | accepted | rejected
            $table->text('body')->nullable();
            $table->timestamp('created_at');
            $table->index(['settlement_case_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_messages');
    }
};
