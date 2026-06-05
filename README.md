# Visora Platform

Visora is a local-first proof-of-concept for event photo discovery and commerce.

The first MVP focuses on local development only:

- Laravel 12 backend at http://127.0.0.1:8002
- Filament admin inside Laravel
- Lunar ecommerce inside Laravel
- Python ML CLI called directly by Laravel
- PostgreSQL pgvector for face similarity search
- PostgreSQL through DBngin locally
- Uploaded photos stored locally first

No cloud storage, production deployment, scaling setup, or live payment settings are included yet.

## Project Structure

```text
visora-platform/
├── apps/
│   ├── backend/       # Laravel + Lunar + Filament
│   └── ml-service/    # Python face search CLI and optional FastAPI dev surface
├── infrastructure/
│   └── docker/
├── docs/
└── scripts/
```

## Local MVP Flow

### Admin

1. Create an event.
2. Upload event photos.
3. Queue photos for face indexing.

### ML

1. Detect faces in uploaded photos.
2. Create face embeddings.
3. Save embeddings and photo references to PostgreSQL pgvector.

### Customer

1. Choose an event.
2. Upload a selfie.
3. Search for matching event photos.

### Commerce

1. Add photo or package to the Lunar cart.
2. Checkout locally.
3. Unlock download after purchase.

## Local Services Draft

The initial local development targets are:

| Service | URL |
| --- | --- |
| Laravel app | http://127.0.0.1:8002 |
| Filament admin | http://127.0.0.1:8002/admin |
| Lunar admin | http://127.0.0.1:8002/lunar |
| Python ML CLI | `apps/ml-service/visora_ml_cli.py` |
| PostgreSQL pgvector | DBngin PostgreSQL `visora` database |
| PostgreSQL via DBngin | 127.0.0.1:5432 |

## Local Database

This local workspace is currently using DBngin PostgreSQL instead of Docker PostgreSQL:

```text
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=visora
DB_USERNAME=postgres
DB_PASSWORD=
```

## Current Status

This repository now contains a generated Laravel backend and Python ML code.

The local `visora` database has been created in DBngin PostgreSQL. Laravel, Filament, and Lunar have been installed and migrated against that database. The ML code currently has a Python CLI with OpenCV YuNet face detection and SFace embeddings. Laravel stores face embeddings in PostgreSQL using pgvector.

The pgvector extension is installed in the local `visora` database, and Laravel queued indexing plus selfie search have been tested through the Python CLI against PostgreSQL vector search.

See `docs/project-status.md` for the current scope, completed work, deferred work, constraints, and next steps.

Local admin credentials:

```text
Email: admin@visora.local
Password: password
```
