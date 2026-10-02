#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET_DIR="${ROOT_DIR}/apps/ml-service/models/body_reid"
TARGET_FILE="${TARGET_DIR}/osnet_x1_0_market1501.pth.tar"

mkdir -p "${TARGET_DIR}"

if [[ -f "${TARGET_FILE}" ]]; then
  echo "OSNet Market-1501 weights already exist:"
  echo "${TARGET_FILE}"
  exit 0
fi

python - <<'PY'
import importlib.util
import sys

if importlib.util.find_spec("gdown") is None:
    print("Missing dependency: gdown")
    print("Install it in .venv-reid with: python -m pip install gdown")
    sys.exit(2)
PY

# Official torchreid model-zoo entry:
# osnet_x1_0, trained on Market1501, rank-1/mAP listed as 94.2/82.6.
gdown "https://drive.google.com/uc?id=1vduhq5DpN2q1g4fYEZfPI17MJeh9qyrA" -O "${TARGET_FILE}"

echo "Downloaded:"
echo "${TARGET_FILE}"
