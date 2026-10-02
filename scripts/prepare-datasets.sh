#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DATASETS_DIR="${ROOT_DIR}/datasets"
EXTRACT=false

if [[ "${1:-}" == "--extract" ]]; then
  EXTRACT=true
fi

mkdir -p \
  "${DATASETS_DIR}/vggface2/raw" \
  "${DATASETS_DIR}/vggface2/train" \
  "${DATASETS_DIR}/vggface2/test" \
  "${DATASETS_DIR}/market-1501/raw" \
  "${DATASETS_DIR}/deepsportradar/raw" \
  "${DATASETS_DIR}/deepsportradar/extracted"

echo "Dataset folders are ready under ${DATASETS_DIR}"

extract_tar_if_present() {
  local archive="$1"
  local target="$2"
  local label="$3"

  if [[ ! -f "${archive}" ]]; then
    echo "Missing ${label}: ${archive}"
    return
  fi

  if [[ "${EXTRACT}" != true ]]; then
    echo "Found ${label}: ${archive}"
    return
  fi

  echo "Extracting ${label}..."
  tar -xzf "${archive}" -C "${target}"
}

extract_zip_if_present() {
  local archive="$1"
  local target="$2"
  local label="$3"

  if [[ ! -f "${archive}" ]]; then
    echo "Missing ${label}: ${archive}"
    return
  fi

  if [[ "${EXTRACT}" != true ]]; then
    echo "Found ${label}: ${archive}"
    return
  fi

  echo "Extracting ${label}..."
  unzip -n "${archive}" -d "${target}"
}

extract_tar_if_present \
  "${DATASETS_DIR}/vggface2/raw/train.tar.gz" \
  "${DATASETS_DIR}/vggface2/train" \
  "VGGFace2 train archive"

extract_tar_if_present \
  "${DATASETS_DIR}/vggface2/raw/test.tar.gz" \
  "${DATASETS_DIR}/vggface2/test" \
  "VGGFace2 test archive"

extract_zip_if_present \
  "${DATASETS_DIR}/market-1501/raw/Market-1501-v15.09.15.zip" \
  "${DATASETS_DIR}/market-1501" \
  "Market-1501 archive"

if compgen -G "${DATASETS_DIR}/deepsportradar/raw/*.zip" > /dev/null; then
  for archive in "${DATASETS_DIR}"/deepsportradar/raw/*.zip; do
    extract_zip_if_present "${archive}" "${DATASETS_DIR}/deepsportradar/extracted" "DeepSportradar archive"
  done
else
  echo "Missing DeepSportradar archives: ${DATASETS_DIR}/deepsportradar/raw/*.zip"
fi

echo "Done. Run: python3 scripts/dataset_inventory.py"
