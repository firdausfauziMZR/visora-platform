from dataclasses import dataclass
from functools import lru_cache
import os
from pathlib import Path
from typing import Any

import numpy as np

EMBEDDING_SIZE = 128
MODEL_ROOT = Path(__file__).resolve().parent / "models"
YUNET_MODEL_PATH = Path(
    os.getenv(
        "VISORA_YUNET_MODEL",
        MODEL_ROOT / "face_detection_yunet" / "face_detection_yunet_2023mar.onnx",
    )
)
SFACE_MODEL_PATH = Path(
    os.getenv(
        "VISORA_SFACE_MODEL",
        MODEL_ROOT / "face_recognition_sface" / "face_recognition_sface_2021dec.onnx",
    )
)


class FaceDetectionDependencyError(RuntimeError):
    pass


class FaceDetectionError(RuntimeError):
    pass


@dataclass(frozen=True)
class FaceBox:
    x: int
    y: int
    width: int
    height: int

    def as_payload(self) -> dict[str, int]:
        return {
            "x": self.x,
            "y": self.y,
            "width": self.width,
            "height": self.height,
        }


@dataclass(frozen=True)
class DetectedFace:
    box: FaceBox
    embedding: list[float]


def _load_cv2() -> Any:
    try:
        import cv2
    except ImportError as exc:
        raise FaceDetectionDependencyError(
            "OpenCV is required for face detection. Install it with: "
            "python -m pip install opencv-python-headless"
        ) from exc

    return cv2


def detect_faces(image_bytes: bytes) -> list[FaceBox]:
    return [face.box for face in detect_faces_with_embeddings(image_bytes)]


def detect_faces_with_embeddings(image_bytes: bytes) -> list[DetectedFace]:
    cv2 = _load_cv2()

    encoded = np.frombuffer(image_bytes, dtype=np.uint8)
    image = cv2.imdecode(encoded, cv2.IMREAD_COLOR)

    if image is None:
        raise FaceDetectionError("Uploaded image could not be decoded.")

    height, width = image.shape[:2]
    detector = _yunet_detector()
    detector.setInputSize((width, height))
    _, detections = detector.detect(image)

    if detections is None:
        return []

    detections = sorted(
        detections,
        key=lambda face: float(face[14]),
        reverse=True,
    )
    detection_pairs = [
        (_detection_box(detection), detection)
        for detection in detections
    ]
    kept_faces = set(_remove_nested_faces([box for box, _ in detection_pairs]))

    return [
        DetectedFace(
            box=box,
            embedding=_face_embedding(image, detection),
        )
        for box, detection in detection_pairs
        if box in kept_faces
    ]


@lru_cache(maxsize=1)
def _yunet_detector() -> Any:
    cv2 = _load_cv2()

    if not YUNET_MODEL_PATH.exists():
        raise FaceDetectionDependencyError(
            f"YuNet model file not found at {YUNET_MODEL_PATH}."
        )

    return cv2.FaceDetectorYN_create(
        str(YUNET_MODEL_PATH),
        "",
        (320, 320),
        score_threshold=0.9,
        nms_threshold=0.3,
        top_k=5000,
    )


@lru_cache(maxsize=1)
def _sface_recognizer() -> Any:
    cv2 = _load_cv2()

    if not SFACE_MODEL_PATH.exists():
        raise FaceDetectionDependencyError(
            f"SFace model file not found at {SFACE_MODEL_PATH}."
        )

    return cv2.FaceRecognizerSF_create(str(SFACE_MODEL_PATH), "")


def _face_embedding(image: Any, detection: Any) -> list[float]:
    recognizer = _sface_recognizer()
    aligned_face = recognizer.alignCrop(image, detection)
    feature = recognizer.feature(aligned_face).flatten().astype(np.float32)
    norm = float(np.linalg.norm(feature))

    if norm > 0:
        feature = feature / norm

    if feature.size != EMBEDDING_SIZE:
        raise FaceDetectionError(
            f"SFace returned {feature.size} values, expected {EMBEDDING_SIZE}."
        )

    return feature.astype(float).tolist()


def _detection_box(detection: Any) -> FaceBox:
    return FaceBox(
        x=max(0, int(round(detection[0]))),
        y=max(0, int(round(detection[1]))),
        width=max(0, int(round(detection[2]))),
        height=max(0, int(round(detection[3]))),
    )


def _remove_nested_faces(faces: list[FaceBox]) -> list[FaceBox]:
    kept: list[FaceBox] = []

    for face in sorted(faces, key=lambda box: box.width * box.height):
        center_x = face.x + face.width / 2
        center_y = face.y + face.height / 2

        if any(
            kept_face.x <= center_x <= kept_face.x + kept_face.width
            and kept_face.y <= center_y <= kept_face.y + kept_face.height
            for kept_face in kept
        ):
            continue

        kept.append(face)

    return kept
