from __future__ import annotations

from dataclasses import dataclass
from functools import lru_cache
import os
import re
import shutil
import subprocess
import tempfile
from typing import Any

import cv2
import numpy as np

OCR_ENGINE = os.getenv("VISORA_OCR_ENGINE", "tesseract").strip().lower()
OCR_MAX_IMAGE_SIDE = int(os.getenv("VISORA_OCR_MAX_IMAGE_SIDE", "1280"))
OCR_MAX_FACE_CROPS = int(os.getenv("VISORA_OCR_MAX_FACE_CROPS", "3"))
OCR_FALLBACK_FULL_IMAGE = os.getenv("VISORA_OCR_FALLBACK_FULL_IMAGE", "true").lower() in {
    "1",
    "true",
    "yes",
    "on",
}
TESSERACT_BINARY = os.getenv("VISORA_TESSERACT_BINARY", "tesseract")
TESSERACT_LANG = os.getenv("VISORA_TESSERACT_LANG", "eng")
TESSERACT_OEM = os.getenv("VISORA_TESSERACT_OEM", "1")
TESSERACT_PSM = os.getenv("VISORA_TESSERACT_PSM", "8")
TESSERACT_TIMEOUT = int(os.getenv("VISORA_TESSERACT_TIMEOUT", "10"))
TESSERACT_MIN_CROP_SIDE = int(os.getenv("VISORA_TESSERACT_MIN_CROP_SIDE", "1200"))
TESSERACT_WHITELIST = os.getenv(
    "VISORA_TESSERACT_WHITELIST",
    "0123456789",
)


class TextRecognitionDependencyError(RuntimeError):
    pass


class TextRecognitionError(RuntimeError):
    pass


@dataclass(frozen=True)
class TextLine:
    text: str
    confidence: float
    box: list[list[float]]


def recognize_text(image_bytes: bytes) -> list[TextLine]:
    encoded = np.frombuffer(image_bytes, dtype=np.uint8)
    image = cv2.imdecode(encoded, cv2.IMREAD_COLOR)

    if image is None:
        raise TextRecognitionError("Uploaded image could not be decoded for OCR.")

    return _read_image_text(image, offset_x=0, offset_y=0, scale=1.0)


def recognize_text_near_faces(image_bytes: bytes, face_boxes: list[Any]) -> list[TextLine]:
    if not face_boxes:
        return recognize_text_regions(image_bytes) if OCR_FALLBACK_FULL_IMAGE else []

    encoded = np.frombuffer(image_bytes, dtype=np.uint8)
    image = cv2.imdecode(encoded, cv2.IMREAD_COLOR)

    if image is None:
        raise TextRecognitionError("Uploaded image could not be decoded for OCR.")

    height, width = image.shape[:2]
    regions = _torso_regions(face_boxes, width, height)
    lines: list[TextLine] = []

    for left, top, right, bottom in regions:
        crop = image[top:bottom, left:right]

        if crop.size == 0:
            continue

        resized, scale = _resize_for_ocr(crop)
        lines.extend(_read_image_text(resized, offset_x=left, offset_y=top, scale=scale))

    if not lines and OCR_FALLBACK_FULL_IMAGE:
        return recognize_text_regions(image_bytes)

    return lines


def recognize_text_regions(image_bytes: bytes) -> list[TextLine]:
    encoded = np.frombuffer(image_bytes, dtype=np.uint8)
    image = cv2.imdecode(encoded, cv2.IMREAD_COLOR)

    if image is None:
        raise TextRecognitionError("Uploaded image could not be decoded for OCR.")

    height, width = image.shape[:2]
    lines: list[TextLine] = []

    for left, top, right, bottom in _fallback_jersey_regions(width, height):
        crop = image[top:bottom, left:right]

        if crop.size == 0:
            continue

        resized, scale = _resize_for_ocr(crop)
        lines.extend(_read_image_text(resized, offset_x=left, offset_y=top, scale=scale))

    return lines


def _read_image_text(image: Any, offset_x: int, offset_y: int, scale: float) -> list[TextLine]:
    if OCR_ENGINE == "tesseract":
        return _read_tesseract_text(image, offset_x=offset_x, offset_y=offset_y, scale=scale)

    if OCR_ENGINE == "easyocr":
        return _read_easyocr_text(_easyocr_reader(), image, offset_x=offset_x, offset_y=offset_y, scale=scale)

    if OCR_ENGINE == "auto":
        if shutil.which(TESSERACT_BINARY):
            return _read_tesseract_text(image, offset_x=offset_x, offset_y=offset_y, scale=scale)

        return _read_easyocr_text(_easyocr_reader(), image, offset_x=offset_x, offset_y=offset_y, scale=scale)

    raise TextRecognitionDependencyError(
        f"Unsupported OCR engine [{OCR_ENGINE}]. Use tesseract, easyocr, or auto."
    )


def _read_easyocr_text(reader: Any, image: Any, offset_x: int, offset_y: int, scale: float) -> list[TextLine]:
    results = reader.readtext(image, detail=1, paragraph=False)
    lines: list[TextLine] = []

    for bbox, text, confidence in results:
        clean = _clean_text(text)

        if clean == "":
            continue

        lines.append(
            TextLine(
                text=clean,
                confidence=float(confidence),
                box=[
                    [
                        float(point[0]) / scale + offset_x,
                        float(point[1]) / scale + offset_y,
                    ]
                    for point in bbox
                ],
            )
        )

    return lines


def _read_tesseract_text(image: Any, offset_x: int, offset_y: int, scale: float) -> list[TextLine]:
    binary = shutil.which(TESSERACT_BINARY)

    if not binary:
        raise TextRecognitionDependencyError(
            "Tesseract is required for bib text recognition. Install it and make sure "
            f"[{TESSERACT_BINARY}] is available on PATH."
        )

    preprocessed = _preprocess_for_tesseract(image)

    with tempfile.NamedTemporaryFile(suffix=".png", delete=True) as image_file:
        if not cv2.imwrite(image_file.name, preprocessed):
            raise TextRecognitionError("Unable to prepare image crop for Tesseract OCR.")

        command = [
            binary,
            image_file.name,
            "stdout",
            "-l",
            TESSERACT_LANG,
            "--oem",
            TESSERACT_OEM,
            "--psm",
            TESSERACT_PSM,
            "-c",
            f"tessedit_char_whitelist={TESSERACT_WHITELIST}",
            "tsv",
        ]

        try:
            result = subprocess.run(
                command,
                check=False,
                capture_output=True,
                text=True,
                timeout=TESSERACT_TIMEOUT,
            )
        except subprocess.TimeoutExpired as exc:
            raise TextRecognitionError("Tesseract OCR timed out.") from exc

    if result.returncode != 0:
        raise TextRecognitionError(result.stderr.strip() or "Tesseract OCR failed.")

    return _parse_tesseract_tsv(result.stdout, offset_x=offset_x, offset_y=offset_y, scale=scale)


def _preprocess_for_tesseract(image: Any) -> Any:
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)

    if TESSERACT_MIN_CROP_SIDE > 0:
        height, width = gray.shape[:2]
        longest_side = max(width, height)

        if longest_side < TESSERACT_MIN_CROP_SIDE:
            scale = TESSERACT_MIN_CROP_SIDE / max(1, longest_side)
            gray = cv2.resize(
                gray,
                (max(1, int(round(width * scale))), max(1, int(round(height * scale)))),
                interpolation=cv2.INTER_CUBIC,
            )

    gray = cv2.bilateralFilter(gray, 5, 55, 55)
    return cv2.adaptiveThreshold(
        gray,
        255,
        cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
        cv2.THRESH_BINARY,
        31,
        9,
    )


def _parse_tesseract_tsv(tsv: str, offset_x: int, offset_y: int, scale: float) -> list[TextLine]:
    rows = [line.split("\t") for line in tsv.splitlines() if line.strip()]

    if len(rows) <= 1:
        return []

    header = rows[0]
    lines: list[TextLine] = []

    try:
        left_index = header.index("left")
        top_index = header.index("top")
        width_index = header.index("width")
        height_index = header.index("height")
        confidence_index = header.index("conf")
        text_index = header.index("text")
    except ValueError as exc:
        raise TextRecognitionError("Tesseract returned an unexpected TSV format.") from exc

    for row in rows[1:]:
        if len(row) <= text_index:
            continue

        clean = _clean_text(row[text_index])

        if clean == "":
            continue

        try:
            confidence = float(row[confidence_index])
            left = float(row[left_index])
            top = float(row[top_index])
            width = float(row[width_index])
            height = float(row[height_index])
        except ValueError:
            continue

        if confidence < 0:
            continue

        right = left + width
        bottom = top + height

        lines.append(
            TextLine(
                text=clean,
                confidence=confidence / 100,
                box=[
                    [left / scale + offset_x, top / scale + offset_y],
                    [right / scale + offset_x, top / scale + offset_y],
                    [right / scale + offset_x, bottom / scale + offset_y],
                    [left / scale + offset_x, bottom / scale + offset_y],
                ],
            )
        )

    return lines


def _torso_regions(face_boxes: list[Any], image_width: int, image_height: int) -> list[tuple[int, int, int, int]]:
    indexed_face_boxes = list(enumerate(face_boxes))

    if OCR_MAX_FACE_CROPS > 0:
        indexed_face_boxes = sorted(
            indexed_face_boxes,
            key=lambda item: _box_attr(item[1], "width") * _box_attr(item[1], "height"),
            reverse=True,
        )[:OCR_MAX_FACE_CROPS]

    regions: list[tuple[int, int, int, int]] = []

    for _, face_box in indexed_face_boxes:
        face_width = max(_box_attr(face_box, "width"), 1)
        face_height = max(_box_attr(face_box, "height"), 1)
        face_x = _box_attr(face_box, "x")
        face_y = _box_attr(face_box, "y")
        center_x = face_x + (face_width / 2)

        regions.extend(
            [
                _clamped_region(
                    center_x=center_x,
                    top=face_y + (face_height * 0.70),
                    width=face_width * 4.4,
                    height=face_height * 3.1,
                    image_width=image_width,
                    image_height=image_height,
                ),
                _clamped_region(
                    center_x=center_x + (face_width * 0.85),
                    top=face_y + (face_height * 0.70),
                    width=face_width * 4.8,
                    height=face_height * 3.1,
                    image_width=image_width,
                    image_height=image_height,
                ),
                _clamped_region(
                    center_x=center_x,
                    top=face_y + (face_height * 0.35),
                    width=face_width * 5.8,
                    height=face_height * 5.2,
                    image_width=image_width,
                    image_height=image_height,
                ),
            ]
        )

    return _dedupe_regions(
        region for region in regions if region[2] - region[0] >= 24 and region[3] - region[1] >= 24
    )


def _fallback_jersey_regions(image_width: int, image_height: int) -> list[tuple[int, int, int, int]]:
    regions = [
        (0.30, 0.24, 0.70, 0.82),
        (0.12, 0.24, 0.52, 0.86),
        (0.48, 0.24, 0.88, 0.86),
        (0.22, 0.44, 0.78, 0.94),
    ]

    return [
        (
            int(round(image_width * left)),
            int(round(image_height * top)),
            int(round(image_width * right)),
            int(round(image_height * bottom)),
        )
        for left, top, right, bottom in regions
    ]


def _clamped_region(
    center_x: float,
    top: float,
    width: float,
    height: float,
    image_width: int,
    image_height: int,
) -> tuple[int, int, int, int]:
    left = int(round(center_x - (width / 2)))
    top = int(round(top))
    right = int(round(left + width))
    bottom = int(round(top + height))

    left = max(0, left)
    top = max(0, top)
    right = min(image_width, right)
    bottom = min(image_height, bottom)

    return (left, top, right, bottom)


def _dedupe_regions(regions: Any) -> list[tuple[int, int, int, int]]:
    unique: list[tuple[int, int, int, int]] = []

    for region in regions:
        if region not in unique:
            unique.append(region)

    return unique


def _resize_for_ocr(image: Any) -> tuple[Any, float]:
    if OCR_MAX_IMAGE_SIDE <= 0:
        return image, 1.0

    height, width = image.shape[:2]
    longest_side = max(width, height)

    if longest_side <= OCR_MAX_IMAGE_SIDE:
        return image, 1.0

    scale = OCR_MAX_IMAGE_SIDE / longest_side

    return (
        cv2.resize(
            image,
            (max(1, int(round(width * scale))), max(1, int(round(height * scale)))),
            interpolation=cv2.INTER_AREA,
        ),
        scale,
    )


def _box_attr(box: Any, name: str) -> int:
    if isinstance(box, dict):
        return int(box[name])

    return int(getattr(box, name))


def bib_candidates(lines: list[TextLine]) -> list[str]:
    candidates: list[str] = []

    for line in lines:
        text = _normalize_for_bib(line.text)

        if not text:
            continue

        if text.isdigit():
            if 1 <= len(text) <= 8:
                candidates.append(text)
            continue

        digit_count = sum(char.isdigit() for char in text)

        if digit_count >= 2 and len(text) <= 12:
            candidates.append(text)

    return sorted(set(candidates))


def text_payload(image_bytes: bytes, face_boxes: list[Any] | None = None) -> dict[str, Any]:
    try:
        lines = recognize_text_near_faces(image_bytes, face_boxes) if face_boxes is not None else recognize_text(image_bytes)
    except TextRecognitionDependencyError as exc:
        return {
            "text_status": "unavailable",
            "text_error": str(exc),
            "text_lines": [],
            "text": "",
            "bib_candidates": [],
        }
    except TextRecognitionError as exc:
        return {
            "text_status": "failed",
            "text_error": str(exc),
            "text_lines": [],
            "text": "",
            "bib_candidates": [],
        }

    return {
        "text_status": "generated" if lines else "skipped",
        "text_lines": [
            {
                "text": line.text,
                "confidence": line.confidence,
                "box": line.box,
            }
            for line in lines
        ],
        "text": " ".join(line.text for line in lines),
        "bib_candidates": bib_candidates(lines),
    }


def _clean_text(text: str) -> str:
    return re.sub(r"\s+", " ", text).strip().upper()


def _normalize_for_bib(text: str) -> str:
    return re.sub(r"[^A-Z0-9]", "", text.upper())


@lru_cache(maxsize=1)
def _easyocr_reader() -> Any:
    try:
        import easyocr
    except ImportError as exc:
        raise TextRecognitionDependencyError(
            "EasyOCR is required for bib text recognition. Install it with: "
            "python -m pip install easyocr"
        ) from exc

    return easyocr.Reader(["en"], gpu=False, verbose=False)
