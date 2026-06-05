# Visora Local Architecture

## Goal

Build a local proof-of-concept for event photo matching and ecommerce before adding cloud storage, production deployment, live payment providers, or scaling concerns.

## Components

### Laravel Backend

Location: `apps/backend`

Responsibilities:

- Event management
- Local photo uploads
- Photo indexing jobs
- Customer event browsing
- Selfie upload handoff to the Python ML CLI
- Lunar cart and checkout flow
- Download unlock after checkout

Laravel will host Filament admin and Lunar ecommerce in the same application for the first MVP.

### Filament Admin

Runs inside the Laravel backend.

Initial admin workflow:

1. Create event.
2. Upload event photos.
3. Trigger or queue indexing.
4. Review indexed photo status.

### Lunar Ecommerce

Runs inside the Laravel backend.

Initial commerce workflow:

1. Customer selects a matched photo or package.
2. Laravel adds the item to a Lunar cart.
3. Customer checks out in local development mode.
4. Laravel marks purchased assets as unlocked.
5. Customer downloads unlocked photos from local storage.

### Python ML CLI

Location: `apps/ml-service`

Responsibilities:

- Accept image indexing commands from Laravel through Symfony Process.
- Detect faces in uploaded event photos.
- Generate embeddings for detected faces.
- Return embeddings to Laravel for storage in PostgreSQL pgvector.
- Accept selfie search commands.
- Return matching photo IDs or asset references.

FastAPI code may remain as an optional development surface, but the current MVP indexing path does not require a running FastAPI service.

### PostgreSQL pgvector

Local vector search inside PostgreSQL for face embeddings.

Draft table purpose:

- Store face embeddings.
- Link each vector to event ID, photo ID, local file reference, and detection metadata.
- Search within a selected event when a customer uploads a selfie.

### PostgreSQL

Primary relational database for Laravel.

For the current local workspace, PostgreSQL is provided by DBngin:

```text
Host: 127.0.0.1
Port: 5432
Database: visora
User: postgres
Password: empty
```

Draft data areas:

- Users and admin accounts
- Events
- Photos
- Indexing jobs and statuses
- Customer search sessions
- Orders and download unlocks
- Lunar ecommerce tables

### Local Photo Storage

Uploaded photos are stored locally first, likely under Laravel storage during the MVP.

Cloud storage is intentionally deferred until the proof-of-concept works locally.

## Local Runtime

```text
Browser
  ├── Laravel app: http://127.0.0.1:8002
  │   ├── Filament admin
  │   ├── Customer event/photo search UI
  │   └── Lunar cart and checkout
  ├── Python ML CLI: apps/ml-service/visora_ml_cli.py
  └── PostgreSQL pgvector inside DBngin
```

## MVP Request Flow

### Photo Indexing

1. Admin creates an event in Filament.
2. Admin uploads photos to Laravel local storage.
3. Laravel creates indexing jobs for uploaded photos.
4. Laravel runs the Python ML CLI with the local photo path.
5. The Python CLI detects faces and creates embeddings.
6. Laravel saves embeddings to PostgreSQL pgvector with event and photo metadata.
7. Laravel records indexing status.

### Customer Search

1. Customer selects an event in Laravel.
2. Customer uploads a selfie.
3. Laravel runs the Python ML CLI with the selfie path and event context.
4. The Python CLI detects the selfie face and creates an embedding.
5. Laravel searches PostgreSQL pgvector within the selected event.
6. Laravel returns matching photo references.
7. Laravel displays matched photos.

### Purchase And Download

1. Customer selects photos or packages.
2. Laravel adds selected items to a Lunar cart.
3. Customer completes local checkout.
4. Laravel records download unlocks.
5. Customer downloads unlocked files from local storage.

## Deferred Until After Local MVP

- S3 or other cloud object storage
- CDN delivery
- Production payment provider configuration
- Production deployment files
- Horizontal scaling
- Multi-region vector search
- Advanced event privacy and consent workflows
