import argparse
import json
import os
import sys
from pathlib import Path

from face_detection import (
    FaceDetectionDependencyError,
    FaceDetectionError,
    detect_faces_with_embeddings,
)
from text_recognition import text_payload
from body_reid import (
    BodyReidDependencyError,
    BodyReidError,
    extract_body_embeddings,
)


def env_bool(name: str, default: bool) -> bool:
    value = os.getenv(name)

    if value is None:
        return default

    return value.strip().lower() in {"1", "true", "yes", "on"}


def skipped_text_payload() -> dict[str, object]:
    return {
        "text_status": "skipped",
        "text": "",
        "text_lines": [],
        "bib_candidates": [],
    }


def skipped_body_reid_payload() -> dict[str, object]:
    return {
        "body_embedding_status": "skipped",
        "body_embeddings_count": 0,
        "body_embeddings": [],
    }


def body_reid_payload(image_bytes: bytes, faces: list[object]) -> dict[str, object]:
    try:
        body_embeddings = extract_body_embeddings(
            image_bytes=image_bytes,
            face_boxes=[face.box for face in faces],
        )
    except BodyReidDependencyError as exc:
        return {
            "body_embedding_status": "unavailable",
            "body_embedding_error": str(exc),
            "body_embeddings_count": 0,
            "body_embeddings": [],
        }
    except BodyReidError as exc:
        return {
            "body_embedding_status": "failed",
            "body_embedding_error": str(exc),
            "body_embeddings_count": 0,
            "body_embeddings": [],
        }

    return {
        "body_embedding_status": "generated" if body_embeddings else "skipped",
        "body_embeddings_count": len(body_embeddings),
        "body_embeddings": [
            {
                "body_index": body.body_index,
                "source_face_index": body.source_face_index,
                "box": body.box.as_payload(),
                "embedding": body.embedding,
            }
            for body in body_embeddings
        ],
    }


def index_photo(args: argparse.Namespace) -> dict[str, object]:
    image_path = Path(args.photo_path)
    image_bytes = image_path.read_bytes()
    faces = detect_faces_with_embeddings(image_bytes)
    text = text_payload(image_bytes, face_boxes=[face.box for face in faces]) if getattr(args, "ocr", True) else skipped_text_payload()
    body_reid = body_reid_payload(image_bytes, faces) if getattr(args, "body_reid", True) else skipped_body_reid_payload()
    filename = args.filename or image_path.name

    return {
        "status": "indexed",
        "event_id": args.event_id,
        "photo_id": args.photo_id,
        "filename": filename,
        "faces_detected": len(faces),
        "faces": [
            {
                "box": face.box.as_payload(),
                "embedding": face.embedding,
            }
            for face in faces
        ],
        "embedding_status": "generated",
        **text,
        **body_reid,
    }


def search_selfie(args: argparse.Namespace) -> dict[str, object]:
    image_path = Path(args.selfie_path)
    image_bytes = image_path.read_bytes()
    faces = detect_faces_with_embeddings(image_bytes)
    text = text_payload(image_bytes, face_boxes=[face.box for face in faces]) if getattr(args, "ocr", True) else skipped_text_payload()
    body_reid = body_reid_payload(image_bytes, faces) if getattr(args, "body_reid", True) else skipped_body_reid_payload()

    return {
        "status": "searched",
        "event_id": args.event_id,
        "filename": args.filename or image_path.name,
        "faces_detected": len(faces),
        "faces": [
            {
                "box": face.box.as_payload(),
                "embedding": face.embedding,
            }
            for face in faces
        ],
        **text,
        **body_reid,
        "matches": [],
    }


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description="Visora local ML CLI for Laravel process execution."
    )
    subparsers = parser.add_subparsers(dest="command", required=True)

    index_parser = subparsers.add_parser("index-photo")
    index_parser.add_argument("--event-id", required=True)
    index_parser.add_argument("--photo-id", required=True)
    index_parser.add_argument("--photo-path", required=True)
    index_parser.add_argument("--filename")
    index_parser.add_argument("--ocr", dest="ocr", action="store_true", default=env_bool("VISORA_AI_OCR_ENABLED", True))
    index_parser.add_argument("--no-ocr", dest="ocr", action="store_false")
    index_parser.add_argument("--body-reid", dest="body_reid", action="store_true", default=env_bool("VISORA_AI_BODY_REID_ENABLED", True))
    index_parser.add_argument("--no-body-reid", dest="body_reid", action="store_false")
    index_parser.set_defaults(handler=index_photo)

    search_parser = subparsers.add_parser("search-selfie")
    search_parser.add_argument("--event-id", required=True)
    search_parser.add_argument("--selfie-path", required=True)
    search_parser.add_argument("--filename")
    search_parser.add_argument("--ocr", dest="ocr", action="store_true", default=env_bool("VISORA_AI_OCR_ENABLED", True))
    search_parser.add_argument("--no-ocr", dest="ocr", action="store_false")
    search_parser.add_argument("--body-reid", dest="body_reid", action="store_true", default=env_bool("VISORA_AI_BODY_REID_ENABLED", True))
    search_parser.add_argument("--no-body-reid", dest="body_reid", action="store_false")
    search_parser.set_defaults(handler=search_selfie)

    return parser


def main() -> int:
    parser = build_parser()
    args = parser.parse_args()

    try:
        payload = args.handler(args)
    except FileNotFoundError as exc:
        print(json.dumps({"error": f"Image file not found: {exc.filename}"}), file=sys.stderr)
        return 2
    except FaceDetectionDependencyError as exc:
        print(json.dumps({"error": str(exc)}), file=sys.stderr)
        return 3
    except FaceDetectionError as exc:
        print(json.dumps({"error": str(exc)}), file=sys.stderr)
        return 4
    print(json.dumps(payload))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
