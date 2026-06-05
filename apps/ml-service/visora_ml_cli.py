import argparse
import json
import sys
from pathlib import Path

from face_detection import (
    FaceDetectionDependencyError,
    FaceDetectionError,
    detect_faces_with_embeddings,
)


def index_photo(args: argparse.Namespace) -> dict[str, object]:
    image_path = Path(args.photo_path)
    faces = detect_faces_with_embeddings(image_path.read_bytes())
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
    }


def search_selfie(args: argparse.Namespace) -> dict[str, object]:
    image_path = Path(args.selfie_path)
    faces = detect_faces_with_embeddings(image_path.read_bytes())

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
    index_parser.set_defaults(handler=index_photo)

    search_parser = subparsers.add_parser("search-selfie")
    search_parser.add_argument("--event-id", required=True)
    search_parser.add_argument("--selfie-path", required=True)
    search_parser.add_argument("--filename")
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
