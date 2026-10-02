#!/usr/bin/env bash
set -euo pipefail

python -m pip install --upgrade pip setuptools wheel
python -m pip install numpy scipy h5py pillow six opencv-python-headless matplotlib future yacs gdown tensorboard
python -m pip install torch torchvision

mkdir -p .external

if [[ ! -d .external/deep-person-reid/.git ]]; then
  git clone https://github.com/KaiyangZhou/deep-person-reid.git .external/deep-person-reid
else
  git -C .external/deep-person-reid pull --ff-only
fi

python - <<'PY'
import sys
from pathlib import Path

repo = Path(".external/deep-person-reid").resolve()
sys.path.insert(0, str(repo))

import numpy
import torch
import torchreid

print("numpy", numpy.__version__)
print("torch", torch.__version__)
print("torchreid loaded")
print("torchreid source", repo)
PY
