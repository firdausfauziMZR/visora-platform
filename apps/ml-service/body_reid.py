from __future__ import annotations

from dataclasses import dataclass
from contextlib import redirect_stdout
from functools import lru_cache
import os
from pathlib import Path
import sys
from typing import Any

import numpy as np

from face_detection import FaceBox


BODY_EMBEDDING_SIZE = 512
MODEL_ROOT = Path(__file__).resolve().parent / "models"
REPO_ROOT = Path(__file__).resolve().parents[2]
TORCHREID_SOURCE = REPO_ROOT / ".external" / "deep-person-reid"
MODEL_PATH = Path(
    os.getenv(
        "VISORA_BODY_REID_MODEL",
        MODEL_ROOT / "body_reid" / "osnet_x1_0_market1501.pth.tar",
    )
)
DEVICE = os.getenv("VISORA_BODY_REID_DEVICE", "cpu")
MIN_CROP_SIDE = int(os.getenv("VISORA_BODY_REID_MIN_CROP_SIDE", "32"))
MAX_FACE_CROPS = int(os.getenv("VISORA_BODY_REID_MAX_FACE_CROPS", "1"))

if TORCHREID_SOURCE.exists():
    sys.path.insert(0, str(TORCHREID_SOURCE))


class BodyReidDependencyError(RuntimeError):
    pass


class BodyReidError(RuntimeError):
    pass


@dataclass(frozen=True)
class BodyEmbedding:
    body_index: int
    source_face_index: int
    box: FaceBox
    embedding: list[float]


def extract_body_embeddings(image_bytes: bytes, face_boxes: list[FaceBox]) -> list[BodyEmbedding]:
    if not face_boxes:
        return []

    cv2 = _load_cv2()
    encoded = np.frombuffer(image_bytes, dtype=np.uint8)
    image = cv2.imdecode(encoded, cv2.IMREAD_COLOR)

    if image is None:
        raise BodyReidError("Uploaded image could not be decoded for body Re-ID.")

    crops: list[np.ndarray] = []
    crop_boxes: list[tuple[int, FaceBox]] = []

    indexed_face_boxes = list(enumerate(face_boxes))

    if MAX_FACE_CROPS > 0:
        indexed_face_boxes = sorted(
            indexed_face_boxes,
            key=lambda item: item[1].width * item[1].height,
            reverse=True,
        )[:MAX_FACE_CROPS]

    for face_index, face_box in indexed_face_boxes:
        body_box = _estimated_body_box(face_box, image.shape[1], image.shape[0])

        if min(body_box.width, body_box.height) < MIN_CROP_SIDE:
            continue

        crop = image[
            body_box.y : body_box.y + body_box.height,
            body_box.x : body_box.x + body_box.width,
        ]

        if crop.size == 0:
            continue

        crops.append(cv2.cvtColor(crop, cv2.COLOR_BGR2RGB))
        crop_boxes.append((face_index, body_box))

    if not crops:
        return []

    output = _feature_extractor()(crops)

    if hasattr(output, "detach"):
        output = output.detach().cpu().numpy()

    matrix = np.asarray(output, dtype=np.float32)
    norms = np.linalg.norm(matrix, axis=1, keepdims=True)
    norms[norms == 0] = 1
    matrix = matrix / norms

    embeddings: list[BodyEmbedding] = []

    for body_index, ((source_face_index, body_box), vector) in enumerate(zip(crop_boxes, matrix)):
        if vector.size != BODY_EMBEDDING_SIZE:
            raise BodyReidError(
                f"OSNet returned {vector.size} values, expected {BODY_EMBEDDING_SIZE}."
            )

        embeddings.append(
            BodyEmbedding(
                body_index=body_index,
                source_face_index=source_face_index,
                box=body_box,
                embedding=vector.astype(float).tolist(),
            )
        )

    return embeddings


def _load_cv2() -> Any:
    try:
        import cv2
    except ImportError as exc:
        raise BodyReidDependencyError(
            "OpenCV is required for body Re-ID. Install it with: "
            "python -m pip install opencv-python-headless"
        ) from exc

    return cv2


@lru_cache(maxsize=1)
def _feature_extractor() -> Any:
    if not MODEL_PATH.exists():
        raise BodyReidDependencyError(
            f"OSNet Market-1501 model file not found at {MODEL_PATH}."
        )

    try:
        from torchreid.utils import FeatureExtractor
    except ImportError as exc:
        raise BodyReidDependencyError(
            "torchreid is required for body Re-ID. Use .venv-reid or run "
            "./scripts/install-reid-deps.sh."
        ) from exc

    with redirect_stdout(sys.stderr):
        return FeatureExtractor(
            model_name="osnet_x1_0",
            model_path=str(MODEL_PATH),
            device=DEVICE,
        )


def _estimated_body_box(face_box: FaceBox, image_width: int, image_height: int) -> FaceBox:
    face_width = max(face_box.width, 1)
    face_height = max(face_box.height, 1)
    center_x = face_box.x + (face_width / 2)

    crop_width = int(face_width * 5.0)
    crop_height = int(face_height * 9.0)
    left = int(round(center_x - (crop_width / 2)))
    top = int(round(face_box.y - (face_height * 1.0)))

    left = max(0, left)
    top = max(0, top)
    right = min(image_width, left + crop_width)
    bottom = min(image_height, top + crop_height)

    if right - left < MIN_CROP_SIDE:
        left = max(0, min(face_box.x, image_width - MIN_CROP_SIDE))
        right = min(image_width, left + MIN_CROP_SIDE)

    if bottom - top < MIN_CROP_SIDE:
        top = max(0, min(face_box.y, image_height - MIN_CROP_SIDE))
        bottom = min(image_height, top + MIN_CROP_SIDE)

    return FaceBox(
        x=left,
        y=top,
        width=max(0, right - left),
        height=max(0, bottom - top),
    )
