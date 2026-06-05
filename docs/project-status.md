# Visora Project Status

This document is the working reference for Visora's local-first build. Refer to it before planning or implementing new work.

Last updated: 2026-06-05

## Current Snapshot

Visora now has a working local Laravel backend, Filament admin shell, Lunar ecommerce admin, DBngin PostgreSQL database with pgvector enabled, and a Python ML CLI with OpenCV YuNet face detection plus SFace embeddings.

MVP Laravel domain models and Filament admin resources have been added for events, event photos, photo indexing jobs, face embeddings, and download unlocks. Uploaded photos can be batch uploaded, queued, or indexed immediately from Filament. Laravel now runs the Python script `apps/ml-service/visora_ml_cli.py` directly through Symfony Process instead of calling FastAPI for indexing. The Python CLI has OpenCV YuNet face detection and SFace 128-dimensional embeddings. Laravel stores those embeddings in PostgreSQL `face_embeddings.embedding vector(128)` and searches them with pgvector cosine distance.

The code paths have been validated by local tests. The Python CLI, Laravel queued indexing path, Laravel pgvector search path, and customer search/Lunar-order/unlock/download flow have also been verified.

Confirmed local services:

| Service | URL | Status |
| --- | --- | --- |
| Laravel app | http://127.0.0.1:8002 | Running and returns `200 OK` |
| Filament admin | http://127.0.0.1:8002/admin | Routes registered |
| Lunar admin | http://127.0.0.1:8002/lunar | Routes registered |
| Python ML CLI | `apps/ml-service/visora_ml_cli.py` | Used by Laravel indexing jobs through Symfony Process |
| FastAPI ML service | http://127.0.0.1:8003 | Legacy/optional dev endpoint; not used by Laravel indexing now |
| PostgreSQL | DBngin on `127.0.0.1:5432` | Database `visora` exists and migrations are current |
| PostgreSQL pgvector | extension `vector` `0.7.4` | Enabled in `visora`; `face_embeddings.embedding` is `vector(128)` |
| Qdrant | http://127.0.0.1:6333 | Legacy/optional; no longer required for current Path 3 build |

Local admin credentials:

```text
Email: admin@visora.local
Password: password
```

Local test datasets:

```text
datasets/lfw/lfw-funneled.tgz
datasets/wider-face/WIDER_train.zip
datasets/wider-face/WIDER_val.zip
datasets/wider-face/WIDER_test.zip
datasets/samples/lfw/
datasets/samples/wider-face/
```

## Project Goal

Visora is a local proof-of-concept for event photo discovery using face search, with a simple ecommerce checkout flow for unlocking photo downloads.

The first milestone is a local MVP, not a production system.

## Required Local Stack

- Laravel 12 backend
- Filament admin inside Laravel
- Lunar ecommerce inside Laravel
- Python ML CLI run by Laravel
- PostgreSQL pgvector
- PostgreSQL
- Local photo storage

## Local Runtime Targets

| Component | Target |
| --- | --- |
| Laravel app | http://127.0.0.1:8002 |
| Filament admin | http://127.0.0.1:8002/admin |
| Lunar admin | http://127.0.0.1:8002/lunar |
| Python ML CLI | `apps/ml-service/visora_ml_cli.py` |
| PostgreSQL pgvector | DBngin PostgreSQL `visora` database |
| PostgreSQL | DBngin on `127.0.0.1:5432` |
| Photo storage | Local filesystem first |

## Current Installed Versions

- Laravel Framework: `12.61.0`
- Filament: `3.3.52`
- Lunar: `1.3.0`
- Livewire: `3.8.1`
- FastAPI: `0.136.3`
- Qdrant client: `1.18.0`

Filament is intentionally pinned to the `3.3` line because Lunar `1.x` requires `filament/filament ^3.3.36`.

## MVP Scope

### Admin

- Create event.
- Upload event photos.
- Queue photos for face indexing.
- Track indexing status.

### ML

- Detect faces in uploaded photos.
- Create face embeddings.
- Store embeddings and photo metadata in PostgreSQL pgvector.
- Search within an event using a customer selfie.

### Customer

- Choose event.
- Upload selfie.
- View matching photos.
- Select photos or packages to buy.

### Commerce

- Add selected photo or package to Lunar cart.
- Run local checkout flow.
- Unlock purchased photo downloads.
- Serve unlocked downloads from local storage.

## Explicitly Not Doing Yet

- Cloud storage such as S3.
- CDN delivery.
- Production deployment files.
- Production payment provider setup.
- Live payment processing.
- Horizontal scaling.
- Production queue infrastructure.
- Multi-region storage or search.
- Advanced consent, privacy, or legal workflows.
- Production-grade monitoring.
- Production-grade security hardening.

These are deferred until the local proof-of-concept works.

## Completed

- Created project folder: `visora-platform`.
- Created base folder structure:
  - `apps/backend`
  - `apps/ml-service`
  - `infrastructure/docker`
  - `docs`
  - `scripts`
- Added `README.md`.
- Added `docs/architecture.md`.
- Added `docs/local-development.md`.
- Added draft `docker-compose.yml`.
- Confirmed DBngin PostgreSQL is running locally on `127.0.0.1:5432`.
- Connected to DBngin PostgreSQL using the `postgres` role.
- Created local PostgreSQL database: `visora`.
- Documented local database settings.
- Generated Laravel 12 app in `apps/backend`.
- Configured Laravel `.env` and `.env.example` for DBngin PostgreSQL.
- Ran base Laravel migrations against the `visora` PostgreSQL database.
- Installed Filament and created an `admin` panel at `/admin`.
- Created local Filament admin user:
  - Email: `admin@visora.local`
  - Password: `password`
- Installed Lunar ecommerce.
- Downgraded Filament to `3.3.52` to match Lunar `1.3.0` compatibility requirements.
- Added Lunar user trait/interface to `App\Models\User`.
- Registered the Lunar admin panel in `AppServiceProvider`.
- Opted out of Lunar telemetry for local development.
- Ran Lunar migrations and default seed/setup data.
- Created local Lunar staff admin:
  - Email: `admin@visora.local`
  - Password: `password`
- Confirmed admin routes exist at `/admin`.
- Confirmed Lunar routes exist at `/lunar`.
- Created FastAPI ML service skeleton in `apps/ml-service`.
- Added ML service endpoints:
  - `GET /health`
  - `POST /index-photo`
  - `POST /search-selfie`
- Created local Python virtual environment in `apps/ml-service/.venv`.
- Installed FastAPI, Uvicorn, python-multipart, and qdrant-client.
- Verified the FastAPI endpoints with in-process test calls.
- Ran Laravel tests successfully.
- Confirmed Laravel serves `200 OK` at `http://127.0.0.1:8002`.
- Confirmed FastAPI health previously returned `ok`; current sandbox cannot curl the user-run FastAPI process even when the port is listening.
- Added Laravel MVP models:
  - `Event`
  - `EventPhoto`
  - `FaceEmbedding`
  - `PhotoIndexingJob`
  - `DownloadUnlock`
- Added migrations for events, event photos, photo indexing jobs, and download unlocks.
- Added Filament admin resources for events, uploaded photos, indexing jobs, and download unlocks.
- Added a Laravel feature test covering the MVP model relationships.
- Added a `PhotoIndexingDispatcher` service to create `PhotoIndexingJob` records and mark photos as queued.
- Added an `IndexEventPhoto` Laravel queue job. Initially this posted to FastAPI; the current implementation runs the Python CLI and stores embeddings in PostgreSQL pgvector:
  - marks indexing jobs as running/completed/failed
  - sends local photo paths to the Python CLI
  - records the ML response payload
  - updates photo status, face count, and indexed timestamp
- Added single-record and bulk Filament actions to queue uploaded/failed photos for indexing.
- Added an Event edit-page photos relation manager for batch photo upload, per-photo indexing, bulk queue indexing, and bulk run-now indexing.
- Added `3s` polling on Event Photos tables so queue/indexing status updates without manual refresh.
- Added Laravel feature tests covering the indexing dispatcher and mocked local ML process.
- Added extracted local sample images from LFW and WIDER FACE under `datasets/samples`.
- Verified LFW archive listing and WIDER FACE train/val/test zip integrity.
- Added a Laravel dataset smoke test that runs one LFW sample and one WIDER FACE sample through the indexing job with the local ML process mocked.
- Added OpenCV-based face detection to the Python ML code:
  - `/index-photo` decodes the uploaded image and returns detected face boxes/counts.
  - `/search-selfie` decodes the uploaded selfie and returns detected face counts.
  - invalid images return `422`.
  - missing OpenCV dependency returns `503` with install guidance.
- Added the earlier simple MVP face embedding generation from normalized 32x32 grayscale face crops, then replaced it with OpenCV Zoo YuNet + SFace.
- Added Qdrant vector-store integration during the earlier Qdrant path:
  - creates the `visora_faces` collection when needed
  - upserts face vectors with `event_id`, `photo_id`, filename, face index, and face box payload
  - searches selfie vectors filtered by `event_id`
- Verified Qdrant collection/upsert/search logic against qdrant-client in-memory mode.
- Installed/launched Docker Desktop locally and started Qdrant in container `visora-qdrant` bound to `127.0.0.1:6333`.
- Verified Qdrant health with `GET http://127.0.0.1:6333/healthz`.
- Verified FastAPI `/index-photo` against real Qdrant using `datasets/samples/lfw/George_HW_Bush_0001.jpg`:
  - event ID: `codex-real-qdrant-test`
  - photo ID: `lfw-george-hw-bush-0001`
  - faces detected: `1`
  - Qdrant collection: `visora_faces`
  - Qdrant points count: `1`
- Verified FastAPI `/search-selfie` against real Qdrant using the same LFW sample:
  - faces detected: `1`
  - returned match: `lfw-george-hw-bush-0001`
- Previously verified real Laravel-to-FastAPI indexing with local DBngin PostgreSQL, FastAPI, and live Qdrant before the process-mode pivot:
  - created test event `codex-real-indexing-test`
  - copied `datasets/samples/lfw/George_HW_Bush_0001.jpg` into Laravel local storage
  - ran `PhotoIndexingDispatcher::runNow()` against the real `ML_SERVICE_URL`
  - backend event ID: `2`
  - backend photo ID: `3`
  - backend photo status: `indexed`
  - indexing job status: `completed`
  - faces detected: `1`
  - Qdrant collection `visora_faces` points count increased to `2`
  - FastAPI `/search-selfie` for event `2` returned match `3`
- Verified queued Laravel indexing with a fresh database queue worker:
  - found and gracefully stopped a stale long-running `php artisan queue:work` process with `php artisan queue:restart`
  - created test event `codex-queued-indexing-test-fresh`
  - queued photo ID `6` through `PhotoIndexingDispatcher::queue()`
  - processed `App\Jobs\IndexEventPhoto` with `php artisan queue:work database --once --tries=1 --timeout=60`
  - backend event ID: `5`
  - backend photo ID: `6`
  - backend photo status: `indexed`
  - indexing job ID: `8`
  - indexing job status: `completed`
  - ML status: `indexed`
  - faces detected: `1`
  - Qdrant status: `stored`
  - Qdrant collection `visora_faces` points count increased to `5`
  - FastAPI `/search-selfie` for event `5` returned match `6`
- Pivoted Laravel indexing from FastAPI HTTP calls to a direct Python CLI process:
  - added `apps/ml-service/visora_ml_cli.py`
  - added `App\Services\LocalMlProcess`
  - changed `IndexEventPhoto` to call Python through Symfony Process
  - updated `PhotoIndexingDispatcher::runNow()` to use Laravel method injection
  - added backend config keys:
    - `VISORA_ML_PYTHON`
    - `VISORA_ML_SCRIPT`
    - `VISORA_ML_TIMEOUT`
  - kept FastAPI code in place as optional legacy/dev surface, but Laravel indexing no longer depends on it
- Previously verified Python CLI direct indexing against live Qdrant before the pgvector pivot:
  - command: `visora_ml_cli.py index-photo`
  - event ID: `cli-process-test`
  - photo ID: `cli-photo-1`
  - faces detected: `1`
  - Qdrant status: `stored`
- Previously verified Python CLI direct selfie search against live Qdrant before the pgvector pivot:
  - command: `visora_ml_cli.py search-selfie`
  - event ID: `cli-process-test`
  - returned match: `cli-photo-1`
- Verified Laravel queued indexing through the Python process path:
  - created test event `codex-process-indexing-test`
  - queued photo ID `7` through `PhotoIndexingDispatcher::queue()`
  - processed `App\Jobs\IndexEventPhoto` with `php artisan queue:work database --once --tries=1 --timeout=90`
  - backend event ID: `6`
  - backend photo ID: `7`
  - indexing job ID: `9`
  - backend photo status: `indexed`
  - indexing job status: `completed`
  - ML status: `indexed`
  - faces detected: `1`
  - Qdrant status: `stored`
  - Qdrant collection `visora_faces` points count increased to `7`
  - Python CLI `search-selfie` for event `6` returned match `7`
- Pivoted vector storage/search from Qdrant to PostgreSQL pgvector for Path 3:
  - confirmed DBngin PostgreSQL `17.0`
  - confirmed pgvector extension `vector` is available as `0.7.4`
  - added migration `2026_06_04_000001_create_face_embeddings_table`
  - enabled `CREATE EXTENSION IF NOT EXISTS vector`
  - added `face_embeddings.embedding vector(128)`
  - added HNSW cosine index on `face_embeddings.embedding`
  - added `App\Models\FaceEmbedding`
  - added event/photo relationships to face embeddings
  - changed Python CLI `index-photo` to return embeddings to Laravel instead of writing vectors to Qdrant
  - changed `IndexEventPhoto` to save returned face embeddings in PostgreSQL
  - added `App\Services\PgvectorFaceSearch` for selfie embedding search via `embedding <=> ?::vector`
- Applied the pgvector migration to the real local `visora` database:
  - pgvector installed version: `0.7.4`
  - `face_embeddings.embedding` column type: `vector`
- Verified real Laravel queued indexing into PostgreSQL pgvector:
  - test event `codex-pgvector-indexing-test`
  - backend event ID: `7`
  - backend photo ID: `8`
  - indexing job ID: `10`
  - backend photo status: `indexed`
  - indexing job status: `completed`
  - ML status: `indexed`
  - faces detected: `1`
  - `face_embeddings` rows for event `7`: `1`
- Verified pgvector cosine search:
  - `PgvectorFaceSearch::searchSelfie()` for event `7`
  - returned photo ID `8`
  - cosine distance: `0`
- Added customer-facing MVP routes and views:
  - `GET /` event list
  - `GET /events/{event:slug}` selfie upload page
  - `POST /events/{event:slug}/search` pgvector selfie search
  - `GET /events/{event:slug}/photos/{photo}/preview` private photo preview
  - `POST /events/{event:slug}/checkout/local` local unlock checkout
  - `GET /events/{event:slug}/photos/{photo}/download/{orderReference}` unlocked download
- Added Lunar-backed local checkout completion flow:
  - customer selects matched photos
  - app creates a Lunar cart, Lunar order, and Lunar order lines for selected photos
  - app creates `DownloadUnlock` records with the Lunar order reference
  - customer can download unlocked photos
- Added customer flow feature test covering search, Lunar order creation, unlock, and download state creation.
- Added ML unittest coverage for FastAPI response shape and Python CLI output shape.
- Ran Laravel tests successfully after the Lunar checkout work:
  - `8` tests passed
  - `42` assertions passed
- Ran ML service pytest coverage:
  - `4` tests passed
- Replaced the placeholder Haar/crop embedding path with OpenCV Zoo YuNet + SFace:
  - added local YuNet and SFace ONNX model files under `apps/ml-service/models`
  - changed Python embeddings to normalized 128-dimensional SFace vectors
  - changed pgvector storage from `vector(1024)` to `vector(128)`
  - existing local face embeddings are cleared by the migration because old crop vectors are not compatible with SFace vectors
- Verified real Laravel-to-Python SFace indexing and pgvector search:
  - test event `sface-smoke-test`
  - backend event ID: `8`
  - backend photo ID: `9`
  - backend photo status: `indexed`
  - faces detected: `1`
  - stored `face_embeddings` rows for photo `9`: `1`
  - search with the same LFW sample returned photo ID `9`
  - cosine distance: `0`

## Current Local Database Settings

```text
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=visora
DB_USERNAME=postgres
DB_PASSWORD=
```

## Not Completed

- Admin event/photo workflows exist as Filament CRUD resources with batch upload, run-now indexing, queue-indexing actions, and table polling.
- If a long-running queue worker was started before backend/ML changes, restart it with `php artisan queue:restart`; stale workers can process jobs with old runtime behavior.
- The current embedding path uses OpenCV Zoo YuNet + SFace. Match quality still needs to be judged with realistic event photos before production use.
- Customer event/selfie/photo matching flow is built for the local MVP.
- Local checkout/download unlock flow is built on Lunar carts/orders. It is still a local paid-state simulation, not live payment processing.
- Laravel is wired to the Python ML CLI for photo indexing through both queued jobs and synchronous run-now actions.

## Current Running Sessions

Current intended local dev services:

- Laravel: `http://127.0.0.1:8002`
- PostgreSQL: DBngin `127.0.0.1:5432`

If this Codex session ends, restart them manually:

```bash
cd /Users/hafeez/Workspaces/visora-platform/apps/backend
php artisan serve --host=127.0.0.1 --port=8002
```

Queue worker:

```bash
cd /Users/hafeez/Workspaces/visora-platform/apps/backend
php artisan queue:work
```

## Current Constraints

- Docker Desktop is installed and Docker CLI is available.
- DBngin PostgreSQL is available and should be used for the local database.
- pgvector is enabled in the local `visora` database.
- PHP and Composer are available through Herd.
- Python 3 is available through Homebrew.
- Qdrant is no longer required for current local Path 3 work.

## Recommended Next Steps

1. Run a queue worker locally when testing indexing from Filament:
   ```bash
   cd /Users/hafeez/Workspaces/visora-platform/apps/backend
   php artisan queue:work
   ```
2. Install ML dependencies locally so OpenCV is available:
   ```bash
   cd /Users/hafeez/Workspaces/visora-platform/apps/ml-service
   source .venv/bin/activate
   python -m pip install -r requirements.txt
   ```
3. Click through the Filament UI manually for upload plus queue-indexing now that the Python process and pgvector paths have passed from the dispatcher and queue worker.
4. Click through the customer flow at `http://127.0.0.1:8002/` and confirm the Lunar order appears in the Lunar admin.
5. Model photo packages/bundles in Lunar if package sales are still part of the MVP.
6. Test SFace match quality against realistic event photos and tune score thresholds/result limits.
7. Add a real payment provider only after the local purchase/unlock workflow is accepted.

## Working Rule

When making Visora decisions, prefer local proof-of-concept progress over production architecture. Do not add cloud services, production deployment files, or live payment configuration until the local MVP is working end to end.
