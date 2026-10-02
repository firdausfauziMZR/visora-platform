#!/usr/bin/env python3
from __future__ import annotations

import argparse
import csv
import re
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
DATASETS = ROOT / "datasets"
REPORTS = ROOT / "reports" / "ml-evaluation" / "body-reid"
IMAGE_EXTENSIONS = {".jpg", ".jpeg", ".png", ".bmp", ".webp"}
MARKET_PATTERN = re.compile(r"^(?P<person_id>-?\d+)_c(?P<camera_id>\d+)")


def build_market_manifest(root: Path) -> list[dict[str, object]]:
    rows = []

    for split in ("bounding_box_train", "query", "bounding_box_test"):
        split_root = root / "Market-1501-v15.09.15" / split

        for image_path in sorted(split_root.glob("*.jpg")):
            match = MARKET_PATTERN.match(image_path.name)

            if not match:
                continue

            person_id = int(match.group("person_id"))

            rows.append(
                {
                    "dataset": "market-1501",
                    "split": split,
                    "identity": person_id,
                    "camera": int(match.group("camera_id")),
                    "path": str(image_path.relative_to(ROOT)),
                    "usable_for_reid": person_id > 0,
                }
            )

    return rows


def build_deepsportradar_manifest(root: Path) -> list[dict[str, object]]:
    rows = []

    for image_path in sorted(root.rglob("*")):
        if not image_path.is_file() or image_path.suffix.lower() not in IMAGE_EXTENSIONS:
            continue

        relative = image_path.relative_to(root)
        parts = relative.parts

        if len(parts) < 3:
            continue

        team_or_game = parts[0]
        identity = parts[1]
        filename = image_path.stem
        camera = filename.split("_", 1)[0] if "_" in filename else ""

        rows.append(
            {
                "dataset": "deepsportradar",
                "split": "all",
                "identity": identity,
                "camera": camera,
                "team_or_game": team_or_game,
                "path": str(image_path.relative_to(ROOT)),
                "usable_for_reid": True,
            }
        )

    return rows


def write_csv(path: Path, rows: list[dict[str, object]], fieldnames: list[str]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)

    with path.open("w", newline="", encoding="utf-8") as file:
        writer = csv.DictWriter(file, fieldnames=fieldnames)
        writer.writeheader()
        writer.writerows(rows)


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="Build body Re-ID CSV manifests for R&D datasets.")
    parser.add_argument(
        "--market-root",
        type=Path,
        default=DATASETS / "market-1501" / "kagglehub",
    )
    parser.add_argument(
        "--deepsportradar-root",
        type=Path,
        default=DATASETS / "deepsportradar" / "kagglehub",
    )

    return parser


def main() -> int:
    args = build_parser().parse_args()
    REPORTS.mkdir(parents=True, exist_ok=True)

    market_rows = build_market_manifest(args.market_root)
    deepsportradar_rows = build_deepsportradar_manifest(args.deepsportradar_root)

    write_csv(
        REPORTS / "market_1501_manifest.csv",
        market_rows,
        ["dataset", "split", "identity", "camera", "path", "usable_for_reid"],
    )
    write_csv(
        REPORTS / "deepsportradar_manifest.csv",
        deepsportradar_rows,
        ["dataset", "split", "identity", "camera", "team_or_game", "path", "usable_for_reid"],
    )

    print(f"Market-1501 rows: {len(market_rows)}")
    print(f"DeepSportradar rows: {len(deepsportradar_rows)}")
    print(f"Reports written to: {REPORTS.relative_to(ROOT)}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
