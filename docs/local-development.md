# Local Development

## Current Local Database

PostgreSQL is running through DBngin, not Docker.

```text
Host: 127.0.0.1
Port: 5432
Database: visora
User: postgres
Password: empty
```

Laravel `.env` database settings should use:

```text
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=visora
DB_USERNAME=postgres
DB_PASSWORD=
```

Laravel calls the local Python ML script directly. These `.env` settings should point at the local virtual environment and CLI script:

```text
VISORA_ML_PYTHON=/Users/hafeez/Workspaces/visora-platform/apps/ml-service/.venv/bin/python
VISORA_ML_SCRIPT=/Users/hafeez/Workspaces/visora-platform/apps/ml-service/visora_ml_cli.py
VISORA_ML_TIMEOUT=60
```

## Current Runtime Status

The database is available locally, Laravel has been generated, the Python ML CLI has OpenCV YuNet face detection plus SFace embeddings, and PostgreSQL pgvector is used for face similarity search.

Current access targets:

```text
Laravel: http://127.0.0.1:8002
Filament admin: http://127.0.0.1:8002/admin
Lunar admin: http://127.0.0.1:8002/lunar
Python ML CLI: /Users/hafeez/Workspaces/visora-platform/apps/ml-service/visora_ml_cli.py
PostgreSQL pgvector: enabled in DBngin database `visora`
```

Local admin credentials:

```text
Email: admin@visora.local
Password: password
```

Qdrant is no longer required for the current Path 3 build. It may remain installed as legacy/optional infrastructure.

Next implementation step: click through upload plus queue-indexing in Filament using the queue worker and PostgreSQL pgvector, then build the customer event/selfie/photo matching flow.

## Start Local Servers

Start Laravel:

```bash
cd /Users/hafeez/Workspaces/visora-platform/apps/backend
php artisan serve --host=127.0.0.1 --port=8002
```

Test the Python ML CLI directly:

```bash
cd /Users/hafeez/Workspaces/visora-platform/apps/ml-service
.venv/bin/python visora_ml_cli.py index-photo --event-id local-smoke --photo-id sample-1 --photo-path ../../datasets/samples/lfw/George_HW_Bush_0001.jpg
```

Start the Laravel queue worker when testing queued indexing:

```bash
cd /Users/hafeez/Workspaces/visora-platform/apps/backend
php artisan queue:work
```
