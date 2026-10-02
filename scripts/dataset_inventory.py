#!/usr/bin/env python3
from __future__ import annotations

from dataclasses import dataclass
import os
from pathlib import Path


IMAGE_EXTENSIONS = {".jpg", ".jpeg", ".png", ".bmp", ".webp"}
ROOT = Path(__file__).resolve().parents[1]
DATASETS = ROOT / "datasets"


@dataclass(frozen=True)
class DatasetSpec:
    name: str
    path: Path
    required_archives: tuple[Path, ...] = ()


def count_images(path: Path) -> int:
    if not path.exists():
        return 0

    total = 0

    for root, _, files in os.walk(path, followlinks=True):
        total += sum(1 for file in files if Path(file).suffix.lower() in IMAGE_EXTENSIONS)

    return total


def count_dirs(path: Path) -> int:
    if not path.exists():
        return 0

    return sum(1 for item in path.iterdir() if item.is_dir())


def status_for(spec: DatasetSpec) -> str:
    if not spec.path.exists():
        return "missing"

    images = count_images(spec.path)

    if images > 0:
        return "ready"

    if any(archive.exists() for archive in spec.required_archives):
        return "archive-present"

    return "empty"


def main() -> int:
    specs = [
        DatasetSpec(
            "WIDER FACE",
            DATASETS / "wider-face",
            (
                DATASETS / "wider-face" / "WIDER_train.zip",
                DATASETS / "wider-face" / "WIDER_val.zip",
                DATASETS / "wider-face" / "WIDER_test.zip",
            ),
        ),
        DatasetSpec("LFW", DATASETS / "lfw", (DATASETS / "lfw" / "lfw-funneled.tgz",)),
        DatasetSpec(
            "VGGFace2",
            DATASETS / "vggface2",
            (
                DATASETS / "vggface2" / "raw" / "train.tar.gz",
                DATASETS / "vggface2" / "raw" / "test.tar.gz",
            ),
        ),
        DatasetSpec(
            "Market-1501",
            DATASETS / "market-1501",
            (DATASETS / "market-1501" / "raw" / "Market-1501-v15.09.15.zip",),
        ),
        DatasetSpec("DeepSportradar", DATASETS / "deepsportradar"),
    ]

    print(f"Dataset root: {DATASETS}")
    print("")
    print(f"{'Dataset':<18} {'Status':<16} {'Images':>10} {'Top dirs':>10}")
    print("-" * 58)

    for spec in specs:
        print(
            f"{spec.name:<18} "
            f"{status_for(spec):<16} "
            f"{count_images(spec.path):>10} "
            f"{count_dirs(spec.path):>10}"
        )

    print("")
    print("Use ./scripts/prepare-datasets.sh to create folders.")
    print("Use ./scripts/prepare-datasets.sh --extract after placing official archives.")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
