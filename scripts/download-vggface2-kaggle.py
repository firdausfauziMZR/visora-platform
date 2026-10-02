#!/usr/bin/env python3
from __future__ import annotations

import argparse
import shutil
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
DEFAULT_TARGET = ROOT / "datasets" / "vggface2" / "kagglehub"


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Download the KaggleHub VGGFace2 mirror into the local Visora datasets folder."
    )
    parser.add_argument(
        "--target",
        type=Path,
        default=DEFAULT_TARGET,
        help=f"Target folder or symlink path. Default: {DEFAULT_TARGET}",
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
        print("Install it with: python3 -m pip install kagglehub")
        return 2

    source = Path(kagglehub.dataset_download("hearfool/vggface2")).resolve()
    target = args.target.resolve()

    target.parent.mkdir(parents=True, exist_ok=True)

    if target.exists() or target.is_symlink():
        print(f"Target already exists: {target}")
        print(f"KaggleHub dataset path: {source}")
        return 0

    if args.copy:
        print(f"Copying VGGFace2 from {source} to {target}...")
        shutil.copytree(source, target)
    else:
        print(f"Linking VGGFace2 from {source} to {target}...")
        target.symlink_to(source, target_is_directory=True)

    print(f"Path to dataset files: {target}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
