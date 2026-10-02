from dataclasses import dataclass
from functools import lru_cache
import os
from pathlib import Path
from typing import Any

import numpy as np

EMBEDDING_SIZE = 128
FULL_IMAGE_SCORE_THRESHOLD = float(os.getenv("VISORA_YUNET_SCORE_THRESHOLD", "0.9"))
TILED_SCORE_THRESHOLD = float(os.getenv("VISORA_YUNET_TILE_SCORE_THRESHOLD", "0.75"))
TILE_SIZE = int(os.getenv("VISORA_YUNET_TILE_SIZE", "640"))
TILE_OVERLAP = float(os.getenv("VISORA_YUNET_TILE_OVERLAP", "0.25"))
TILE_SCALE = float(os.getenv("VISORA_YUNET_TILE_SCALE", "2.0"))
DEDUP_IOU_THRESHOLD = float(os.getenv("VISORA_FACE_DEDUP_IOU_THRESHOLD", "0.35"))
MAX_IMAGE_SIDE = int(os.getenv("VISORA_FACE_MAX_IMAGE_SIDE", "1920"))
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


@dataclass(frozen=True)
class DetectionCandidate:
    box: FaceBox
    detection: Any
    score: float


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

    detection_image, detection_scale = _resize_for_detection(image)
    candidates = _detect_candidates(detection_image)

    if detection_scale != 1.0:
        candidates = [
            _scale_candidate_to_original(candidate, detection_scale)
            for candidate in candidates
        ]

    candidates = _deduplicate_candidates(candidates)

    return [
        DetectedFace(
            box=candidate.box,
            embedding=_face_embedding(image, candidate.detection),
        )
        for candidate in candidates
    ]


def _resize_for_detection(image: Any) -> tuple[Any, float]:
    if MAX_IMAGE_SIDE <= 0:
        return image, 1.0

    cv2 = _load_cv2()
    height, width = image.shape[:2]
    longest_side = max(width, height)

    if longest_side <= MAX_IMAGE_SIDE:
        return image, 1.0

    scale = MAX_IMAGE_SIDE / longest_side

    return (
        cv2.resize(
            image,
            (max(1, int(round(width * scale))), max(1, int(round(height * scale)))),
            interpolation=cv2.INTER_AREA,
        ),
        scale,
    )


def _scale_candidate_to_original(candidate: DetectionCandidate, scale: float) -> DetectionCandidate:
    mapped_detection = _map_detection_to_original(candidate.detection, 0, 0, scale)

    return DetectionCandidate(
        box=_detection_box(mapped_detection),
        detection=mapped_detection,
        score=candidate.score,
    )


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
        score_threshold=FULL_IMAGE_SCORE_THRESHOLD,
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


def _detect_candidates(image: Any) -> list[DetectionCandidate]:
    height, width = image.shape[:2]

    candidates = _detect_region(
        image=image,
        offset_x=0,
        offset_y=0,
        scale=1.0,
        score_threshold=FULL_IMAGE_SCORE_THRESHOLD,
    )

    if max(width, height) <= TILE_SIZE:
        return candidates

    for x, y, tile_width, tile_height in _tile_regions(width, height):
        tile = image[y : y + tile_height, x : x + tile_width]
        scale = _tile_scale(tile_width, tile_height)

        candidates.extend(
            _detect_region(
                image=tile,
                offset_x=x,
                offset_y=y,
                scale=scale,
                score_threshold=TILED_SCORE_THRESHOLD,
            )
        )

    return candidates


def _detect_region(
    image: Any,
    offset_x: int,
    offset_y: int,
    scale: float,
    score_threshold: float,
) -> list[DetectionCandidate]:
    cv2 = _load_cv2()
    detector = _yunet_detector()
    detection_image = image

    if scale != 1.0:
        detection_image = cv2.resize(
            image,
            None,
            fx=scale,
            fy=scale,
            interpolation=cv2.INTER_LINEAR,
        )

    height, width = detection_image.shape[:2]
    detector.setInputSize((width, height))

    if hasattr(detector, "setScoreThreshold"):
        detector.setScoreThreshold(score_threshold)

    _, detections = detector.detect(detection_image)

    if detections is None:
        return []

    return [
        DetectionCandidate(
            box=_detection_box(mapped_detection),
            detection=mapped_detection,
            score=float(mapped_detection[14]),
        )
        for mapped_detection in (
            _map_detection_to_original(detection, offset_x, offset_y, scale)
            for detection in detections
        )
    ]


def _tile_regions(width: int, height: int) -> list[tuple[int, int, int, int]]:
    step = max(1, int(TILE_SIZE * (1 - TILE_OVERLAP)))
    x_positions = _axis_positions(width, TILE_SIZE, step)
    y_positions = _axis_positions(height, TILE_SIZE, step)

    return [
        (x, y, min(TILE_SIZE, width - x), min(TILE_SIZE, height - y))
        for y in y_positions
        for x in x_positions
    ]


def _axis_positions(length: int, tile_size: int, step: int) -> list[int]:
    if length <= tile_size:
        return [0]

    positions = list(range(0, max(1, length - tile_size + 1), step))
    last_position = length - tile_size

    if positions[-1] != last_position:
        positions.append(last_position)

    return positions


def _tile_scale(width: int, height: int) -> float:
    if TILE_SCALE <= 1.0:
        return 1.0

    max_dimension = max(width, height)

    if max_dimension * TILE_SCALE > 1280:
        return 1280 / max_dimension

    return TILE_SCALE


def _map_detection_to_original(
    detection: Any,
    offset_x: int,
    offset_y: int,
    scale: float,
) -> Any:
    mapped = np.array(detection, dtype=np.float32, copy=True)

    for index in range(0, 14, 2):
        mapped[index] = mapped[index] / scale + offset_x
        mapped[index + 1] = mapped[index + 1] / scale + offset_y

    mapped[2] = detection[2] / scale
    mapped[3] = detection[3] / scale

    return mapped


def _detection_box(detection: Any) -> FaceBox:
    return FaceBox(
        x=max(0, int(round(detection[0]))),
        y=max(0, int(round(detection[1]))),
        width=max(0, int(round(detection[2]))),
        height=max(0, int(round(detection[3]))),
    )


def _deduplicate_candidates(candidates: list[DetectionCandidate]) -> list[DetectionCandidate]:
    kept: list[DetectionCandidate] = []

    for candidate in sorted(candidates, key=lambda face: face.score, reverse=True):
        if any(_face_iou(candidate.box, kept_candidate.box) >= DEDUP_IOU_THRESHOLD for kept_candidate in kept):
            continue

        kept.append(candidate)

    return kept


def _face_iou(first: FaceBox, second: FaceBox) -> float:
    intersection_x1 = max(first.x, second.x)
    intersection_y1 = max(first.y, second.y)
    intersection_x2 = min(first.x + first.width, second.x + second.width)
    intersection_y2 = min(first.y + first.height, second.y + second.height)

    intersection_width = max(0, intersection_x2 - intersection_x1)
    intersection_height = max(0, intersection_y2 - intersection_y1)
    intersection_area = intersection_width * intersection_height

    if intersection_area == 0:
        return 0.0

    first_area = first.width * first.height
    second_area = second.width * second.height
    union_area = first_area + second_area - intersection_area

    return intersection_area / union_area if union_area > 0 else 0.0
