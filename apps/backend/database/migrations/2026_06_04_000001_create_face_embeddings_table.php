<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        }

        Schema::create('face_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_photo_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('face_index');
            $table->json('box')->nullable();
            $table->timestamps();

            $table->unique(['event_photo_id', 'face_index']);
            $table->index(['event_id', 'event_photo_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE face_embeddings ADD COLUMN embedding vector(128) NOT NULL');
            DB::statement('CREATE INDEX face_embeddings_embedding_hnsw_idx ON face_embeddings USING hnsw (embedding vector_cosine_ops)');

            return;
        }

        Schema::table('face_embeddings', function (Blueprint $table) {
            $table->text('embedding');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_embeddings');
    }
};
