# Visora ML Service

Local Python ML code for Visora face indexing, body Re-ID embeddings, and selfie search.

## Laravel Process Mode

Laravel calls `visora_ml_cli.py` directly through Symfony Process for the current MVP. FastAPI is no longer required for indexing.

```bash
python3 -m venv .venv
source .venv/bin/activate
python -m pip install -r requirements.txt
python visora_ml_cli.py index-photo --event-id local-smoke --photo-id sample-1 --photo-path ../../datasets/samples/lfw/George_HW_Bush_0001.jpg
python visora_ml_cli.py search-selfie --event-id local-smoke --selfie-path ../../datasets/samples/lfw/George_HW_Bush_0001.jpg
```

For body Re-ID indexing, use the project-level `.venv-reid` environment because it contains PyTorch and torchreid:

```bash
cd /Users/hafeez/Workspaces/visora-platform
source .venv-reid/bin/activate
python apps/ml-service/visora_ml_cli.py index-photo --event-id local-smoke --photo-id sample-1 --photo-path datasets/samples/lfw/George_HW_Bush_0001.jpg
```

## Optional FastAPI Run

```bash
python3 -m venv .venv
source .venv/bin/activate
python -m pip install -r requirements.txt
uvicorn main:app --host 127.0.0.1 --port 8003 --reload
```

## Optional FastAPI Endpoints

- `GET /health`
- `POST /index-photo`
- `POST /search-selfie`

`/index-photo` and `/search-selfie` now run OpenCV Zoo YuNet face detection and SFace embedding extraction. The Laravel CLI path also extracts OSNet body Re-ID embeddings from estimated body crops around detected faces when torchreid and the Market-1501 weights are available.

The optional FastAPI `/index-photo` endpoint can still store vectors in Qdrant for legacy testing.

The current Laravel path does not use FastAPI or Qdrant; Laravel calls `visora_ml_cli.py` and stores returned embeddings in PostgreSQL pgvector.

This is a local OpenCV Zoo face-recognition path for MVP validation. Match quality still needs to be tested against realistic event photos before production use.

If face-detection requests return `503`, install the OpenCV dependency inside the ML virtual environment:

```bash
cd /Users/hafeez/Workspaces/visora-platform/apps/ml-service
source .venv/bin/activate
python -m pip install -r requirements.txt
```

If you are specifically testing the optional FastAPI/Qdrant legacy path and receive a Qdrant `503`, start Qdrant locally:

```bash
docker start visora-qdrant
```

The local Qdrant container is named `visora-qdrant` and is bound to `127.0.0.1:6333`.
