#!/usr/bin/env python3
from __future__ import annotations

import argparse
import csv
import json
import random
import statistics
import sys
import time
from dataclasses import dataclass
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
ML_SERVICE = ROOT / "apps" / "ml-service"
DATASETS = ROOT / "datasets"
REPORTS = ROOT / "reports" / "ml-evaluation"
IMAGE_EXTENSIONS = {".jpg", ".jpeg", ".png", ".bmp", ".webp"}

sys.path.insert(0, str(ML_SERVICE))

from face_detection import detect_faces_with_embeddings  # noqa: E402


@dataclass(frozen=True)
class EmbeddingSample:
    identity: str
    path: Path
    embedding: list[float]


def image_files(root: Path) -> list[Path]:
    return sorted(
        path
        for path in root.rglob("*")
        if path.is_file() and path.suffix.lower() in IMAGE_EXTENSIONS
    )


def cosine_similarity(first: list[float], second: list[float]) -> float:
    return sum(left * right for left, right in zip(first, second, strict=True))


def evaluate_wider(args: argparse.Namespace) -> int:
    root = args.root
    images = image_files(root)

    if not images:
        raise SystemExit(f"No images found in {root}")

    rng = random.Random(args.seed)
    selected = rng.sample(images, min(args.limit, len(images)))
    rows = []

    started = time.perf_counter()

    for index, image_path in enumerate(selected, start=1):
        image_started = time.perf_counter()

        try:
            faces = detect_faces_with_embeddings(image_path.read_bytes())
            error = ""
        except Exception as exc:  # noqa: BLE001 - report evaluation failures per file.
            faces = []
            error = str(exc)

        rows.append(
            {
                "path": str(image_path.relative_to(ROOT)),
                "event_class": image_path.parent.name,
                "faces_detected": len(faces),
                "seconds": round(time.perf_counter() - image_started, 4),
                "error": error,
            }
        )

        if args.progress and index % args.progress == 0:
            print(f"Processed {index}/{len(selected)} WIDER FACE images...")

    output_dir = REPORTS / "wider-face"
    output_dir.mkdir(parents=True, exist_ok=True)
    csv_path = output_dir / "detection_sample.csv"
    json_path = output_dir / "detection_summary.json"

    write_csv(csv_path, rows, ["path", "event_class", "faces_detected", "seconds", "error"])

    successful = [row for row in rows if not row["error"]]
    face_counts = [int(row["faces_detected"]) for row in successful]
    event_summary = {}

    for row in successful:
        event_summary.setdefault(row["event_class"], {"images": 0, "faces_detected": 0})
        event_summary[row["event_class"]]["images"] += 1
        event_summary[row["event_class"]]["faces_detected"] += int(row["faces_detected"])

    summary = {
        "dataset": "WIDER FACE",
        "root": str(root.relative_to(ROOT)),
        "sampled_images": len(selected),
        "successful_images": len(successful),
        "failed_images": len(rows) - len(successful),
        "total_faces_detected": sum(face_counts),
        "average_faces_per_image": round(statistics.mean(face_counts), 4) if face_counts else 0,
        "median_faces_per_image": statistics.median(face_counts) if face_counts else 0,
        "elapsed_seconds": round(time.perf_counter() - started, 4),
        "event_classes": event_summary,
        "csv_report": str(csv_path.relative_to(ROOT)),
    }

    json_path.write_text(json.dumps(summary, indent=2), encoding="utf-8")
    print(json.dumps(summary, indent=2))

    return 0


def evaluate_vggface2(args: argparse.Namespace) -> int:
    root = args.root
    identities = [
        path
        for path in sorted((root / args.split).iterdir())
        if path.is_dir() and image_files(path)
    ]

    if not identities:
        raise SystemExit(f"No VGGFace2 identity folders found in {root / args.split}")

    rng = random.Random(args.seed)
    selected_identities = rng.sample(identities, min(args.identities, len(identities)))
    samples: list[EmbeddingSample] = []
    skipped = []

    for identity_dir in selected_identities:
        for image_path in image_files(identity_dir)[: args.images_per_identity]:
            try:
                faces = detect_faces_with_embeddings(image_path.read_bytes())
            except Exception as exc:  # noqa: BLE001
                skipped.append({"path": str(image_path.relative_to(ROOT)), "error": str(exc)})
                continue

            if not faces:
                skipped.append({"path": str(image_path.relative_to(ROOT)), "error": "no face detected"})
                continue

            samples.append(
                EmbeddingSample(
                    identity=identity_dir.name,
                    path=image_path,
                    embedding=faces[0].embedding,
                )
            )

    positive_pairs = []
    negative_pairs = []
    by_identity: dict[str, list[EmbeddingSample]] = {}

    for sample in samples:
        by_identity.setdefault(sample.identity, []).append(sample)

    for identity, identity_samples in by_identity.items():
        if len(identity_samples) < 2:
            continue

        for first_index in range(len(identity_samples) - 1):
            positive_pairs.append((identity_samples[first_index], identity_samples[first_index + 1]))

    identities_with_samples = [identity for identity, identity_samples in by_identity.items() if identity_samples]

    for index, identity in enumerate(identities_with_samples):
        other_identity = identities_with_samples[(index + 1) % len(identities_with_samples)]

        if identity == other_identity:
            continue

        negative_pairs.append((by_identity[identity][0], by_identity[other_identity][0]))

    pair_rows = []

    for label, pairs in (("positive", positive_pairs), ("negative", negative_pairs)):
        for first, second in pairs:
            pair_rows.append(
                {
                    "label": label,
                    "first_identity": first.identity,
                    "second_identity": second.identity,
                    "first_path": str(first.path.relative_to(ROOT)),
                    "second_path": str(second.path.relative_to(ROOT)),
                    "cosine_similarity": round(cosine_similarity(first.embedding, second.embedding), 6),
                }
            )

    output_dir = REPORTS / "vggface2"
    output_dir.mkdir(parents=True, exist_ok=True)
    csv_path = output_dir / "verification_pairs.csv"
    json_path = output_dir / "verification_summary.json"

    write_csv(
        csv_path,
        pair_rows,
        [
            "label",
            "first_identity",
            "second_identity",
            "first_path",
            "second_path",
            "cosine_similarity",
        ],
    )

    positives = [row["cosine_similarity"] for row in pair_rows if row["label"] == "positive"]
    negatives = [row["cosine_similarity"] for row in pair_rows if row["label"] == "negative"]

    summary = {
        "dataset": "VGGFace2",
        "root": str(root.relative_to(ROOT)),
        "split": args.split,
        "sampled_identities": len(selected_identities),
        "embedded_images": len(samples),
        "skipped_images": len(skipped),
        "positive_pairs": len(positives),
        "negative_pairs": len(negatives),
        "positive_similarity_average": round(statistics.mean(positives), 6) if positives else None,
        "negative_similarity_average": round(statistics.mean(negatives), 6) if negatives else None,
        "positive_similarity_min": min(positives) if positives else None,
        "negative_similarity_max": max(negatives) if negatives else None,
        "csv_report": str(csv_path.relative_to(ROOT)),
    }

    json_path.write_text(json.dumps(summary, indent=2), encoding="utf-8")
    (output_dir / "skipped_images.json").write_text(json.dumps(skipped, indent=2), encoding="utf-8")
    print(json.dumps(summary, indent=2))

    return 0


def write_csv(path: Path, rows: list[dict[str, object]], fieldnames: list[str]) -> None:
    with path.open("w", newline="", encoding="utf-8") as file:
        writer = csv.DictWriter(file, fieldnames=fieldnames)
        writer.writeheader()
        writer.writerows(rows)


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="Evaluate Visora's current face ML pipeline.")
    subparsers = parser.add_subparsers(dest="command", required=True)

    wider = subparsers.add_parser("wider-detection", help="Run face detection over a WIDER FACE image sample.")
    wider.add_argument("--root", type=Path, default=DATASETS / "wider-face" / "WIDER_train" / "images")
    wider.add_argument("--limit", type=int, default=100)
    wider.add_argument("--seed", type=int, default=42)
    wider.add_argument("--progress", type=int, default=25)
    wider.set_defaults(handler=evaluate_wider)

    vgg = subparsers.add_parser("vggface2-verification", help="Run SFace similarity checks on VGGFace2 samples.")
    vgg.add_argument("--root", type=Path, default=DATASETS / "vggface2" / "kagglehub")
    vgg.add_argument("--split", default="train")
    vgg.add_argument("--identities", type=int, default=20)
    vgg.add_argument("--images-per-identity", type=int, default=4)
    vgg.add_argument("--seed", type=int, default=42)
    vgg.set_defaults(handler=evaluate_vggface2)

    return parser


def main() -> int:
    parser = build_parser()
    args = parser.parse_args()

    return args.handler(args)


if __name__ == "__main__":
    raise SystemExit(main())
