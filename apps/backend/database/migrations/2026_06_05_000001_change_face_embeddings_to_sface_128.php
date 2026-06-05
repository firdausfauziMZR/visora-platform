<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS face_embeddings_embedding_hnsw_idx');
        DB::table('face_embeddings')->delete();
        DB::statement('ALTER TABLE face_embeddings ALTER COLUMN embedding TYPE vector(128) USING embedding::vector(128)');
        DB::statement('CREATE INDEX face_embeddings_embedding_hnsw_idx ON face_embeddings USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS face_embeddings_embedding_hnsw_idx');
        DB::table('face_embeddings')->delete();
        DB::statement('ALTER TABLE face_embeddings ALTER COLUMN embedding TYPE vector(1024) USING embedding::vector(1024)');
        DB::statement('CREATE INDEX face_embeddings_embedding_hnsw_idx ON face_embeddings USING hnsw (embedding vector_cosine_ops)');
    }
};
