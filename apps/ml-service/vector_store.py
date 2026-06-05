import os
from functools import lru_cache
from uuid import uuid5, NAMESPACE_URL

from qdrant_client import QdrantClient
from qdrant_client.http import models

from face_detection import DetectedFace


VECTOR_SIZE = 128
COLLECTION_NAME = os.getenv("QDRANT_COLLECTION", "visora_faces")
QDRANT_URL = os.getenv("QDRANT_URL", "http://127.0.0.1:6333")


class VectorStoreError(RuntimeError):
    pass


@lru_cache(maxsize=1)
def qdrant_client() -> QdrantClient:
    return QdrantClient(url=QDRANT_URL)


def ensure_collection(client: QdrantClient | None = None) -> None:
    client = client or qdrant_client()

    try:
        collections = client.get_collections()
        exists = any(
            collection.name == COLLECTION_NAME
            for collection in collections.collections
        )

        if exists:
            return

        client.create_collection(
            collection_name=COLLECTION_NAME,
            vectors_config=models.VectorParams(
                size=VECTOR_SIZE,
                distance=models.Distance.COSINE,
            ),
        )
    except Exception as exc:
        raise VectorStoreError(f"Qdrant collection setup failed: {exc}") from exc


def upsert_faces(
    *,
    event_id: str,
    photo_id: str,
    filename: str,
    faces: list[DetectedFace],
    client: QdrantClient | None = None,
) -> None:
    client = client or qdrant_client()
    ensure_collection(client)

    points = [
        models.PointStruct(
            id=str(uuid5(NAMESPACE_URL, f"visora:{event_id}:{photo_id}:{index}")),
            vector=face.embedding,
            payload={
                "event_id": event_id,
                "photo_id": photo_id,
                "filename": filename,
                "face_index": index,
                "box": face.box.as_payload(),
            },
        )
        for index, face in enumerate(faces)
    ]

    if not points:
        return

    try:
        client.upsert(
            collection_name=COLLECTION_NAME,
            points=points,
        )
    except Exception as exc:
        raise VectorStoreError(f"Qdrant face upsert failed: {exc}") from exc


def search_faces(
    *,
    event_id: str,
    face: DetectedFace,
    limit: int = 20,
    client: QdrantClient | None = None,
) -> list[str]:
    client = client or qdrant_client()
    ensure_collection(client)

    try:
        response = client.query_points(
            collection_name=COLLECTION_NAME,
            query=face.embedding,
            query_filter=models.Filter(
                must=[
                    models.FieldCondition(
                        key="event_id",
                        match=models.MatchValue(value=event_id),
                    ),
                ],
            ),
            limit=limit,
            with_payload=True,
        )
    except Exception as exc:
        raise VectorStoreError(f"Qdrant face search failed: {exc}") from exc

    photo_ids: list[str] = []

    for result in response.points:
        photo_id = result.payload.get("photo_id") if result.payload else None

        if photo_id and photo_id not in photo_ids:
            photo_ids.append(str(photo_id))

    return photo_ids
