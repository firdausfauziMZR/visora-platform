#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TORCHREID_DIR="${ROOT_DIR}/.external/deep-person-reid"
REID_DATA_DIR="${ROOT_DIR}/.external/reid-data"
SAVE_DIR="${ROOT_DIR}/reports/ml-evaluation/body-reid/training/osnet_x1_0_market1501"
MAX_EPOCH="${MAX_EPOCH:-10}"
EVAL_FREQ="${EVAL_FREQ:-5}"
PRINT_FREQ="${PRINT_FREQ:-20}"
BATCH_SIZE="${BATCH_SIZE:-32}"

if [[ ! -d "${TORCHREID_DIR}" ]]; then
  echo "Missing ${TORCHREID_DIR}. Run ./scripts/install-reid-deps.sh first."
  exit 1
fi

"${ROOT_DIR}/scripts/prepare-reid-data.sh"

cd "${TORCHREID_DIR}"

PYTHONPATH="${TORCHREID_DIR}:${PYTHONPATH:-}" python scripts/main.py \
  --config-file configs/im_osnet_x1_0_softmax_256x128_amsgrad_cosine.yaml \
  --transforms random_flip random_erase \
  --root "${REID_DATA_DIR}" \
  data.save_dir "${SAVE_DIR}" \
  train.max_epoch "${MAX_EPOCH}" \
  train.batch_size "${BATCH_SIZE}" \
  train.print_freq "${PRINT_FREQ}" \
  test.eval_freq "${EVAL_FREQ}"
