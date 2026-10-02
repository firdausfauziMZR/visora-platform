#!/usr/bin/env python3
from __future__ import annotations

import argparse
import shutil
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
DATASETS = ROOT / "datasets"


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Download a KaggleHub dataset into a local Visora dataset folder."
    )
    parser.add_argument(
        "slug",
        help='Kaggle dataset slug, for example "hearfool/vggface2".',
    )
    parser.add_argument(
        "dataset",
        help='Local dataset folder name under datasets/, for example "market-1501".',
    )
    parser.add_argument(
        "--name",
        default="kagglehub",
        help='Link/copy folder name inside the dataset folder. Default: "kagglehub".',
    )
    parser.add_argument(
        "--copy",
        action="store_true",
        help="Copy files into the target instead of creating a symlink to KaggleHub's cache.",
    )
    args = parser.parse_args()

    try:
        import kagglehub
    except ImportError:
        print("Missing dependency: kagglehub")
        print("Install it inside the dataset venv with: python -m pip install kagglehub")
        return 2

    source = Path(kagglehub.dataset_download(args.slug)).resolve()
    target = (DATASETS / args.dataset / args.name).resolve()
    target.parent.mkdir(parents=True, exist_ok=True)

    if target.exists() or target.is_symlink():
        print(f"Target already exists: {target}")
        print(f"KaggleHub dataset path: {source}")
        return 0

    if args.copy:
        print(f"Copying {args.slug} from {source} to {target}...")
        shutil.copytree(source, target)
    else:
        print(f"Linking {args.slug} from {source} to {target}...")
        target.symlink_to(source, target_is_directory=True)

    print(f"Path to dataset files: {target}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
