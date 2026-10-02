#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import random
import statistics
import sys
import time
from collections import defaultdict
from pathlib import Path

import numpy as np


ROOT = Path(__file__).resolve().parents[1]
ML_SERVICE = ROOT / "apps" / "ml-service"
DEFAULT_SOURCE = ROOT / "datasets" / "social-event"
DEFAULT_OUTPUT = ML_SERVICE / "models" / "face_calibration" / "social_event_face_calibration.json"

sys.path.insert(0, str(ML_SERVICE))

from face_detection import _sface_recognizer  # noqa: E402


def normalize(vector: np.ndarray) -> np.ndarray:
    vector = vector.astype(np.float32, copy=False)
    norm = float(np.linalg.norm(vector))

    if norm > 0:
        vector = vector / norm

    return vector


def cosine_similarity(left: np.ndarray, right: np.ndarray) -> float:
    return float(np.dot(left, right))


def load_event_embeddings(event_dir: Path) -> tuple[list[str], list[np.ndarray], dict[str, str]]:
    ground_path = event_dir / "ground.npy"
    output_path = event_dir / "output.npz"

    if not ground_path.exists() or not output_path.exists():
        raise FileNotFoundError(f"Missing ground/output files in {event_dir}")

    ground = np.load(ground_path, allow_pickle=True)
    output = np.load(output_path, allow_pickle=True)

    face_labels = {str(face_id): str(person_id) for face_id, person_id in ground}
    face_ids = [str(face_id) for face_id in output["data_faceid"]]
    face_images = output["data_face_img"]

    recognizer = _sface_recognizer()
    embeddings: list[np.ndarray] = []
    labels: list[str] = []
    skipped = 0

    for face_id, face_image in zip(face_ids, face_images, strict=True):
        label = face_labels.get(face_id)

        if not label or label == "-1":
            skipped += 1
            continue

        feature = recognizer.feature(face_image).flatten()

        if feature.size != 128:
            skipped += 1
            continue

        embeddings.append(normalize(feature))
        labels.append(label)

    return labels, embeddings, face_labels


def sample_pair_scores(
    labels: list[str],
    embeddings: list[np.ndarray],
    rng: random.Random,
    negative_multiplier: int = 4,
    negative_cap: int = 25000,
) -> tuple[list[float], list[float]]:
    by_label: dict[str, list[np.ndarray]] = defaultdict(list)

    for label, embedding in zip(labels, embeddings, strict=True):
        by_label[label].append(embedding)

    positive_scores: list[float] = []

    for vectors in by_label.values():
        if len(vectors) < 2:
            continue

        for left_index in range(len(vectors) - 1):
            left = vectors[left_index]
            for right_index in range(left_index + 1, len(vectors)):
                positive_scores.append(cosine_similarity(left, vectors[right_index]))

    if not positive_scores:
        return [], []

    unique_labels = [label for label, vectors in by_label.items() if vectors]
    negative_target = min(max(len(positive_scores) * negative_multiplier, 2000), negative_cap)
    negative_scores: list[float] = []

    while len(negative_scores) < negative_target and len(unique_labels) > 1:
        left_label, right_label = rng.sample(unique_labels, 2)
        left = rng.choice(by_label[left_label])
        right = rng.choice(by_label[right_label])
        negative_scores.append(cosine_similarity(left, right))

    return positive_scores, negative_scores


def choose_threshold(positive_scores: list[float], negative_scores: list[float]) -> dict[str, float]:
    if not positive_scores or not negative_scores:
        return {
            "minimum_similarity": 0.55,
            "maximum_cosine_distance": 0.45,
            "f1_score": 0.0,
            "precision": 0.0,
            "recall": 0.0,
            "true_positive_rate": 0.0,
            "false_positive_rate": 0.0,
        }

    candidates = sorted(set(positive_scores + negative_scores))

    if len(candidates) > 750:
        quantiles = np.linspace(0.0, 1.0, 751)
        candidates = [float(np.quantile(candidates, q)) for q in quantiles]

    best = {
        "minimum_similarity": 0.55,
        "maximum_cosine_distance": 0.45,
        "f1_score": -1.0,
        "precision": 0.0,
        "recall": 0.0,
        "true_positive_rate": 0.0,
        "false_positive_rate": 0.0,
    }

    positives_total = len(positive_scores)
    negatives_total = len(negative_scores)

    for threshold in candidates:
        true_positives = sum(score >= threshold for score in positive_scores)
        false_positives = sum(score >= threshold for score in negative_scores)
        false_negatives = positives_total - true_positives
        true_negatives = negatives_total - false_positives

        precision = true_positives / (true_positives + false_positives) if (true_positives + false_positives) else 0.0
        recall = true_positives / positives_total if positives_total else 0.0
        f1_score = (
            2 * precision * recall / (precision + recall)
            if (precision + recall)
            else 0.0
        )
        true_positive_rate = recall
        false_positive_rate = false_positives / negatives_total if negatives_total else 0.0

        if f1_score > best["f1_score"]:
            best = {
                "minimum_similarity": float(threshold),
                "maximum_cosine_distance": float(1 - threshold),
                "f1_score": float(f1_score),
                "precision": float(precision),
                "recall": float(recall),
                "true_positive_rate": float(true_positive_rate),
                "false_positive_rate": float(false_positive_rate),
                "true_positives": int(true_positives),
                "false_positives": int(false_positives),
                "false_negatives": int(false_negatives),
                "true_negatives": int(true_negatives),
            }

    return best


def main() -> int:
    parser = argparse.ArgumentParser(description="Train a social-event face calibration artifact from labeled archive data.")
    parser.add_argument("--source", type=Path, default=DEFAULT_SOURCE)
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT)
    parser.add_argument("--limit-events", type=int, default=0)
    parser.add_argument("--seed", type=int, default=42)
    args = parser.parse_args()

    if not args.source.exists():
        print(f"Source directory not found: {args.source}", file=sys.stderr)
        return 2

    event_dirs = sorted(path for path in args.source.iterdir() if path.is_dir())

    if args.limit_events > 0:
        event_dirs = event_dirs[: args.limit_events]

    if not event_dirs:
        print("No event folders found.", file=sys.stderr)
        return 3

    rng = random.Random(args.seed)
    started = time.perf_counter()
    event_payloads: dict[str, dict[str, object]] = {}
    global_positive_scores: list[float] = []
    global_negative_scores: list[float] = []
    summary_rows: list[dict[str, object]] = []

    for event_dir in event_dirs:
        labels, embeddings, face_labels = load_event_embeddings(event_dir)
        positive_scores, negative_scores = sample_pair_scores(labels, embeddings, rng=rng)

        if positive_scores:
            global_positive_scores.extend(positive_scores)
        if negative_scores:
            global_negative_scores.extend(negative_scores)

        stats = choose_threshold(positive_scores, negative_scores)
        event_code = event_dir.name

        event_payloads[event_code] = {
            "minimum_similarity": stats["minimum_similarity"],
            "maximum_cosine_distance": stats["maximum_cosine_distance"],
            "faces_evaluated": len(labels),
            "positive_pairs": len(positive_scores),
            "negative_pairs": len(negative_scores),
            "precision": stats["precision"],
            "recall": stats["recall"],
            "f1_score": stats["f1_score"],
            "source": event_code,
        }

        summary_rows.append(
            {
                "event_code": event_code,
                "faces_evaluated": len(labels),
                "positive_pairs": len(positive_scores),
                "negative_pairs": len(negative_scores),
                "minimum_similarity": round(stats["minimum_similarity"], 6),
                "maximum_cosine_distance": round(stats["maximum_cosine_distance"], 6),
                "precision": round(stats["precision"], 6),
                "recall": round(stats["recall"], 6),
                "f1_score": round(stats["f1_score"], 6),
            }
        )

        print(
            f"{event_code}: faces={len(labels)} positives={len(positive_scores)} negatives={len(negative_scores)} "
            f"threshold={stats['minimum_similarity']:.4f} f1={stats['f1_score']:.4f}"
        )

    global_stats = choose_threshold(global_positive_scores, global_negative_scores)

    output_payload = {
        "model": "sface-128",
        "generated_at": int(time.time()),
        "source_root": str(args.source),
        "events": event_payloads,
        "global": {
            "minimum_similarity": global_stats["minimum_similarity"],
            "maximum_cosine_distance": global_stats["maximum_cosine_distance"],
            "faces_evaluated": len(global_positive_scores) + len(global_negative_scores),
            "positive_pairs": len(global_positive_scores),
            "negative_pairs": len(global_negative_scores),
            "precision": global_stats["precision"],
            "recall": global_stats["recall"],
            "f1_score": global_stats["f1_score"],
        },
        "summary": summary_rows,
        "elapsed_seconds": round(time.perf_counter() - started, 4),
    }

    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(output_payload, indent=2), encoding="utf-8")

    print(json.dumps(output_payload["global"], indent=2))
    print(f"Wrote calibration artifact to {args.output}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
