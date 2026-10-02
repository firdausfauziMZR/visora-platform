#!/usr/bin/env python3
from __future__ import annotations

import argparse
import csv
import json
import random
import sys
from dataclasses import dataclass
from pathlib import Path
from typing import Any

import numpy as np


ROOT = Path(__file__).resolve().parents[1]
REPORTS = ROOT / "reports" / "ml-evaluation" / "body-reid"
MARKET_MANIFEST = REPORTS / "market_1501_manifest.csv"
TORCHREID_SOURCE = ROOT / ".external" / "deep-person-reid"
DEFAULT_MARKET1501_WEIGHTS = ROOT / "apps" / "ml-service" / "models" / "body_reid" / "osnet_x1_0_market1501.pth.tar"

if TORCHREID_SOURCE.exists():
    sys.path.insert(0, str(TORCHREID_SOURCE))


@dataclass(frozen=True)
class BodySample:
    dataset: str
    split: str
    identity: str
    camera: str
    path: Path


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


def load_feature_extractor(model_name: str, model_path: str, device: str) -> Any:
    try:
        from torchreid.utils import FeatureExtractor
    except ImportError as exc:
        raise SystemExit(
            "Missing torchreid. Create a PyTorch body Re-ID environment first. "
            "See docs/ml-evaluation.md for setup commands."
        ) from exc

    return FeatureExtractor(
        model_name=model_name,
        model_path=model_path,
        device=device,
    )


def extract_features(extractor: Any, samples: list[BodySample], batch_size: int, normalize: bool) -> np.ndarray:
    features = []

    for start in range(0, len(samples), batch_size):
        batch = samples[start : start + batch_size]
        output = extractor([str(sample.path) for sample in batch])

        if hasattr(output, "detach"):
            output = output.detach().cpu().numpy()

        features.append(np.asarray(output, dtype=np.float32))

    matrix = np.vstack(features)
    if normalize:
        norms = np.linalg.norm(matrix, axis=1, keepdims=True)
        norms[norms == 0] = 1

        return matrix / norms

    return matrix


def rank_gallery(query_features: np.ndarray, gallery_features: np.ndarray, metric: str) -> tuple[np.ndarray, np.ndarray]:
    if metric == "cosine":
        scores = query_features @ gallery_features.T
        ranked_indices = np.argsort(-scores, axis=1)

        return ranked_indices, scores

    query_squared = np.sum(query_features**2, axis=1, keepdims=True)
    gallery_squared = np.sum(gallery_features**2, axis=1)
    distances = query_squared + gallery_squared - (2 * query_features @ gallery_features.T)
    ranked_indices = np.argsort(distances, axis=1)

    return ranked_indices, distances


def sample_gallery_by_identity(samples: list[BodySample], per_identity: int, rng: random.Random) -> list[BodySample]:
    if per_identity <= 0:
        return samples

    by_identity: dict[str, list[BodySample]] = {}

    for sample in samples:
        by_identity.setdefault(sample.identity, []).append(sample)

    selected = []

    for identity_samples in by_identity.values():
        selected.extend(rng.sample(identity_samples, min(per_identity, len(identity_samples))))

    return selected


def evaluate_market(args: argparse.Namespace) -> int:
    rows = read_manifest(args.manifest)
    queries = [sample for sample in rows if sample.split == "query"]
    gallery = [sample for sample in rows if sample.split == "bounding_box_test"]
    rng = random.Random(args.seed)

    if not queries or not gallery:
        raise SystemExit("Market-1501 manifest must include query and bounding_box_test rows.")

    queries = rng.sample(queries, min(args.queries, len(queries)))
    gallery = sample_gallery_by_identity(gallery, args.gallery_per_identity, rng)
    model_path = args.model_path

    if not model_path and DEFAULT_MARKET1501_WEIGHTS.exists():
        model_path = str(DEFAULT_MARKET1501_WEIGHTS)

    extractor = load_feature_extractor(args.model_name, model_path, args.device)

    normalize = args.metric == "cosine"

    print(f"Extracting query features: {len(queries)} images")
    query_features = extract_features(extractor, queries, args.batch_size, normalize)

    print(f"Extracting gallery features: {len(gallery)} images")
    gallery_features = extract_features(extractor, gallery, args.batch_size, normalize)

    ranked_indices_by_query, scores = rank_gallery(query_features, gallery_features, args.metric)
    result_rows = []
    top1_hits = 0
    top5_hits = 0

    for query_index, query in enumerate(queries):
        ranked = [
            index
            for index in ranked_indices_by_query[query_index]
            if gallery[index].camera != query.camera
        ][: args.top_k]

        if not ranked:
            continue

        ranked_identities = [gallery[index].identity for index in ranked]
        top1_hit = ranked_identities[0] == query.identity
        top5_hit = query.identity in ranked_identities[:5]

        top1_hits += int(top1_hit)
        top5_hits += int(top5_hit)

        result_rows.append(
            {
                "query_path": str(query.path.relative_to(ROOT)),
                "query_identity": query.identity,
                "query_camera": query.camera,
                "top1_identity": ranked_identities[0],
                "top1_score": round(float(scores[query_index, ranked[0]]), 6),
                "top1_hit": top1_hit,
                "top5_hit": top5_hit,
            }
        )

    output_dir = REPORTS / "torchreid"
    output_dir.mkdir(parents=True, exist_ok=True)
    csv_path = output_dir / "market_1501_osnet.csv"
    json_path = output_dir / "market_1501_osnet_summary.json"

    write_csv(
        csv_path,
        result_rows,
        ["query_path", "query_identity", "query_camera", "top1_identity", "top1_score", "top1_hit", "top5_hit"],
    )

    evaluated = len(result_rows)
    summary = {
        "dataset": "Market-1501",
        "model": args.model_name,
        "model_path": model_path or "imagenet-pretrained",
        "metric": args.metric,
        "device": args.device,
        "queries": evaluated,
        "gallery": len(gallery),
        "top1_accuracy": round(top1_hits / evaluated, 6) if evaluated else 0,
        "top5_accuracy": round(top5_hits / evaluated, 6) if evaluated else 0,
        "report": str(csv_path.relative_to(ROOT)),
    }

    json_path.write_text(json.dumps(summary, indent=2), encoding="utf-8")
    print(json.dumps(summary, indent=2))

    return 0


def write_csv(path: Path, rows: list[dict[str, object]], fieldnames: list[str]) -> None:
    with path.open("w", newline="", encoding="utf-8") as file:
        writer = csv.DictWriter(file, fieldnames=fieldnames)
        writer.writeheader()
        writer.writerows(rows)


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="Evaluate a torchreid body Re-ID model.")
    parser.add_argument("--manifest", type=Path, default=MARKET_MANIFEST)
    parser.add_argument("--model-name", default="osnet_x1_0")
    parser.add_argument(
        "--model-path",
        default="",
        help=(
            "Optional Re-ID model weights. If omitted, uses "
            "apps/ml-service/models/body_reid/osnet_x1_0_market1501.pth.tar when present; "
            "otherwise falls back to ImageNet-pretrained OSNet."
        ),
    )
    parser.add_argument("--device", default="cpu")
    parser.add_argument("--queries", type=int, default=100)
    parser.add_argument("--gallery-per-identity", type=int, default=4)
    parser.add_argument("--top-k", type=int, default=5)
    parser.add_argument("--batch-size", type=int, default=32)
    parser.add_argument(
        "--metric",
        choices=["euclidean", "cosine"],
        default="euclidean",
        help="Distance metric for ranking gallery images. Torchreid model-zoo evaluation uses euclidean.",
    )
    parser.add_argument("--seed", type=int, default=42)

    return parser


def main() -> int:
    args = build_parser().parse_args()

    return evaluate_market(args)


if __name__ == "__main__":
    raise SystemExit(main())
