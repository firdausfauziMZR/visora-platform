#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REID_DATA_DIR="${ROOT_DIR}/.external/reid-data"
MARKET_SOURCE="${ROOT_DIR}/datasets/market-1501/kagglehub"
MARKET_TARGET="${REID_DATA_DIR}/market1501"

if [[ ! -d "${MARKET_SOURCE}/Market-1501-v15.09.15" ]]; then
  echo "Market-1501 source not found: ${MARKET_SOURCE}/Market-1501-v15.09.15"
  echo "Download it first, then rerun this script."
  exit 1
fi

mkdir -p "${REID_DATA_DIR}"

if [[ -L "${MARKET_TARGET}" || -e "${MARKET_TARGET}" ]]; then
  rm -rf "${MARKET_TARGET}"
fi

ln -s "${MARKET_SOURCE}" "${MARKET_TARGET}"

echo "Prepared torchreid data root:"
echo "${REID_DATA_DIR}"
echo ""
echo "Market-1501 linked at:"
echo "${MARKET_TARGET}"
