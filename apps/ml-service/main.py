from typing import Annotated

from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from pydantic import BaseModel, Field

from face_detection import (
    FaceDetectionDependencyError,
    FaceDetectionError,
    detect_faces_with_embeddings,
)
from vector_store import VectorStoreError, search_faces, upsert_faces


app = FastAPI(
    title="Visora ML Service",
    description="Local-first face indexing and search service for Visora.",
    version="0.1.0",
)


class HealthResponse(BaseModel):
    status: str = "ok"
    service: str = "visora-ml-service"


class IndexResponse(BaseModel):
    status: str
    event_id: str
    photo_id: str
    filename: str
    faces_detected: int
    faces: list[dict[str, int]] = Field(default_factory=list)
    qdrant_status: str = "stored"


class SearchResponse(BaseModel):
    status: str
    event_id: str
    filename: str
    faces_detected: int = 0
    matches: list[str] = Field(
        default_factory=list,
        description="Placeholder photo IDs until Qdrant search is implemented.",
    )


@app.get("/health", response_model=HealthResponse)
def health() -> HealthResponse:
    return HealthResponse()


@app.post("/index-photo", response_model=IndexResponse)
async def index_photo(
    event_id: Annotated[str, Form()],
    photo_id: Annotated[str, Form()],
    photo: Annotated[UploadFile, File()],
) -> IndexResponse:
    image_bytes = await photo.read()

    try:
        faces = detect_faces_with_embeddings(image_bytes)
    except FaceDetectionDependencyError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc
    except FaceDetectionError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc

    filename = photo.filename or "uploaded-photo"

    try:
        upsert_faces(
            event_id=event_id,
            photo_id=photo_id,
            filename=filename,
            faces=faces,
        )
    except VectorStoreError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc

    return IndexResponse(
        status="indexed",
        event_id=event_id,
        photo_id=photo_id,
        filename=filename,
        faces_detected=len(faces),
        faces=[face.box.as_payload() for face in faces],
    )


@app.post("/search-selfie", response_model=SearchResponse)
async def search_selfie(
    event_id: Annotated[str, Form()],
    selfie: Annotated[UploadFile, File()],
) -> SearchResponse:
    image_bytes = await selfie.read()

    try:
        faces = detect_faces_with_embeddings(image_bytes)
    except FaceDetectionDependencyError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc
    except FaceDetectionError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc

    matches: list[str] = []

    if faces:
        try:
            matches = search_faces(event_id=event_id, face=faces[0])
        except VectorStoreError as exc:
            raise HTTPException(status_code=503, detail=str(exc)) from exc

    return SearchResponse(
        status="searched",
        event_id=event_id,
        filename=selfie.filename or "uploaded-selfie",
        faces_detected=len(faces),
        matches=matches,
    )
