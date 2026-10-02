#!/usr/bin/env python3
from __future__ import annotations

import argparse
import csv
import json
import random
import sys
from dataclasses import dataclass
from pathlib import Path

import numpy as np


ROOT = Path(__file__).resolve().parents[1]
REPORTS = ROOT / "reports" / "ml-evaluation" / "body-reid"
MARKET_MANIFEST = REPORTS / "market_1501_manifest.csv"
DEEPSPORTRADAR_MANIFEST = REPORTS / "deepsportradar_manifest.csv"


@dataclass(frozen=True)
class BodySample:
    dataset: str
    split: str
    identity: str
    camera: str
    path: Path


def load_cv2():
    try:
        import cv2
    except ImportError as exc:
        raise SystemExit(
            "OpenCV is required. Run with apps/ml-service/.venv/bin/python or install opencv-python-headless."
        ) from exc

    return cv2


def read_manifest(path: Path) -> list[BodySample]:
    with path.open(newline="", encoding="utf-8") as file:
        rows = csv.DictReader(file)

        return [
            BodySample(
                dataset=row["dataset"],
                split=row["split"],
                identity=str(row["identity"]),
                camera=str(row["camera"]),
                path=ROOT / row["path"],
            )
            for row in rows
            if row.get("usable_for_reid") == "True"
        ]


def hsv_histogram(path: Path) -> np.ndarray:
    cv2 = load_cv2()
    image = cv2.imread(str(path))

    if image is None:
        raise ValueError(f"Unable to read image: {path}")

    image = cv2.resize(image, (96, 192), interpolation=cv2.INTER_AREA)
    hsv = cv2.cvtColor(image, cv2.COLOR_BGR2HSV)

    # Split vertically so the descriptor captures rough shirt/shorts color layout.
    upper = hsv[:96, :]
    lower = hsv[96:, :]
    features = []

    for region in (upper, lower):
        hist = cv2.calcHist([region], [0, 1, 2], None, [16, 8, 4], [0, 180, 0, 256, 0, 256])
        hist = cv2.normalize(hist, hist).flatten()
        features.append(hist)

    vector = np.concatenate(features).astype(np.float32)
    norm = float(np.linalg.norm(vector))

    return vector / norm if norm > 0 else vector


def cosine_similarity(first: np.ndarray, second: np.ndarray) -> float:
    return float(np.dot(first, second))


def evaluate_market(args: argparse.Namespace) -> int:
    rows = read_manifest(args.manifest)
    queries = [sample for sample in rows if sample.split == "query"]
    gallery = [sample for sample in rows if sample.split == "bounding_box_test"]
    rng = random.Random(args.seed)

    if not queries or not gallery:
        raise SystemExit("Market-1501 manifest must include query and bounding_box_test rows.")

    queries = rng.sample(queries, min(args.queries, len(queries)))

    if args.gallery_per_identity > 0:
        gallery = sample_gallery_by_identity(gallery, args.gallery_per_identity, rng)

    embeddings: dict[Path, np.ndarray] = {}

    def embedding(sample: BodySample) -> np.ndarray:
        if sample.path not in embeddings:
            embeddings[sample.path] = hsv_histogram(sample.path)

        return embeddings[sample.path]

    result_rows = []
    top1_hits = 0
    top5_hits = 0
    evaluated = 0

    for query in queries:
        query_embedding = embedding(query)
        candidates = [
            (
                candidate,
                cosine_similarity(query_embedding, embedding(candidate)),
            )
            for candidate in gallery
            if candidate.camera != query.camera
        ]
        candidates.sort(key=lambda item: item[1], reverse=True)

        if not candidates:
            continue

        ranked = candidates[: args.top_k]
        identities = [candidate.identity for candidate, _ in ranked]
        top1_hit = identities[0] == query.identity
        top5_hit = query.identity in identities[:5]

        top1_hits += int(top1_hit)
        top5_hits += int(top5_hit)
        evaluated += 1

        result_rows.append(
            {
                "query_path": str(query.path.relative_to(ROOT)),
                "query_identity": query.identity,
                "query_camera": query.camera,
                "top1_identity": identities[0],
                "top1_score": round(ranked[0][1], 6),
                "top1_hit": top1_hit,
                "top5_hit": top5_hit,
            }
        )

    output_dir = REPORTS / "baseline"
    output_dir.mkdir(parents=True, exist_ok=True)
    write_csv(
        output_dir / "market_1501_color_baseline.csv",
        result_rows,
        ["query_path", "query_identity", "query_camera", "top1_identity", "top1_score", "top1_hit", "top5_hit"],
    )

    summary = {
        "dataset": "Market-1501",
        "baseline": "HSV color histogram",
        "queries": evaluated,
        "gallery": len(gallery),
        "top1_accuracy": round(top1_hits / evaluated, 6) if evaluated else 0,
        "top5_accuracy": round(top5_hits / evaluated, 6) if evaluated else 0,
        "report": str((output_dir / "market_1501_color_baseline.csv").relative_to(ROOT)),
    }

    (output_dir / "market_1501_color_baseline_summary.json").write_text(json.dumps(summary, indent=2), encoding="utf-8")
    print(json.dumps(summary, indent=2))

    return 0


def evaluate_deepsportradar(args: argparse.Namespace) -> int:
    rows = read_manifest(args.manifest)
    rng = random.Random(args.seed)
    by_identity: dict[str, list[BodySample]] = {}

    for row in rows:
        by_identity.setdefault(row.identity, []).append(row)

    identities = [identity for identity, samples in by_identity.items() if len(samples) >= 2]
    identities = rng.sample(identities, min(args.identities, len(identities)))

    pairs = []

    for index, identity in enumerate(identities):
        samples = rng.sample(by_identity[identity], 2)
        pairs.append(("positive", samples[0], samples[1]))

        other_identity = identities[(index + 1) % len(identities)]

        if other_identity != identity:
            pairs.append(("negative", samples[0], rng.choice(by_identity[other_identity])))

    result_rows = []
    positives = []
    negatives = []

    for label, first, second in pairs:
        score = cosine_similarity(hsv_histogram(first.path), hsv_histogram(second.path))

        if label == "positive":
            positives.append(score)
        else:
            negatives.append(score)

        result_rows.append(
            {
                "label": label,
                "first_identity": first.identity,
                "second_identity": second.identity,
                "first_camera": first.camera,
                "second_camera": second.camera,
                "first_path": str(first.path.relative_to(ROOT)),
                "second_path": str(second.path.relative_to(ROOT)),
                "cosine_similarity": round(score, 6),
            }
        )

    output_dir = REPORTS / "baseline"
    output_dir.mkdir(parents=True, exist_ok=True)
    write_csv(
        output_dir / "deepsportradar_color_pairs.csv",
        result_rows,
        [
            "label",
            "first_identity",
            "second_identity",
            "first_camera",
            "second_camera",
            "first_path",
            "second_path",
            "cosine_similarity",
        ],
    )

    summary = {
        "dataset": "DeepSportradar",
        "baseline": "HSV color histogram",
        "sampled_identities": len(identities),
        "positive_pairs": len(positives),
        "negative_pairs": len(negatives),
        "positive_similarity_average": round(float(np.mean(positives)), 6) if positives else None,
        "negative_similarity_average": round(float(np.mean(negatives)), 6) if negatives else None,
        "report": str((output_dir / "deepsportradar_color_pairs.csv").relative_to(ROOT)),
    }

    (output_dir / "deepsportradar_color_baseline_summary.json").write_text(json.dumps(summary, indent=2), encoding="utf-8")
    print(json.dumps(summary, indent=2))

    return 0


def sample_gallery_by_identity(samples: list[BodySample], per_identity: int, rng: random.Random) -> list[BodySample]:
    by_identity: dict[str, list[BodySample]] = {}

    for sample in samples:
        by_identity.setdefault(sample.identity, []).append(sample)

    selected = []

    for identity_samples in by_identity.values():
        selected.extend(rng.sample(identity_samples, min(per_identity, len(identity_samples))))

    return selected


def write_csv(path: Path, rows: list[dict[str, object]], fieldnames: list[str]) -> None:
    with path.open("w", newline="", encoding="utf-8") as file:
        writer = csv.DictWriter(file, fieldnames=fieldnames)
        writer.writeheader()
        writer.writerows(rows)


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="Evaluate simple body Re-ID baselines.")
    subparsers = parser.add_subparsers(dest="command", required=True)

    market = subparsers.add_parser("market-1501", help="Evaluate Market-1501 query retrieval.")
    market.add_argument("--manifest", type=Path, default=MARKET_MANIFEST)
    market.add_argument("--queries", type=int, default=100)
    market.add_argument("--gallery-per-identity", type=int, default=4)
    market.add_argument("--top-k", type=int, default=5)
    market.add_argument("--seed", type=int, default=42)
    market.set_defaults(handler=evaluate_market)

    deepsport = subparsers.add_parser("deepsportradar", help="Evaluate DeepSportradar positive/negative pairs.")
    deepsport.add_argument("--manifest", type=Path, default=DEEPSPORTRADAR_MANIFEST)
    deepsport.add_argument("--identities", type=int, default=50)
    deepsport.add_argument("--seed", type=int, default=42)
    deepsport.set_defaults(handler=evaluate_deepsportradar)

    return parser


def main() -> int:
    parser = build_parser()
    args = parser.parse_args()

    return args.handler(args)


if __name__ == "__main__":
    raise SystemExit(main())
