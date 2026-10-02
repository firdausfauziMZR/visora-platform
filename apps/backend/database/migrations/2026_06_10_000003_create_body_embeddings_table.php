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

        Schema::create('body_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_photo_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('body_index');
            $table->unsignedSmallInteger('source_face_index')->nullable();
            $table->json('box')->nullable();
            $table->timestamps();

            $table->unique(['event_photo_id', 'body_index']);
            $table->index(['event_id', 'event_photo_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE body_embeddings ADD COLUMN embedding vector(512) NOT NULL');
            DB::statement('CREATE INDEX body_embeddings_embedding_hnsw_idx ON body_embeddings USING hnsw (embedding vector_cosine_ops)');

            return;
        }

        Schema::table('body_embeddings', function (Blueprint $table) {
            $table->text('embedding');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('body_embeddings');
    }
};
