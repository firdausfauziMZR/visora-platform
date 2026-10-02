# ML Evaluation Workflow

Use this before training new models. It measures how the current Visora ML pipeline performs against the downloaded R&D datasets.

## Face Detection: WIDER FACE

Run a small smoke test:

```bash
apps/ml-service/.venv/bin/python scripts/evaluate_face_pipeline.py wider-detection --limit 10
```

Run a larger sample:

```bash
apps/ml-service/.venv/bin/python scripts/evaluate_face_pipeline.py wider-detection --limit 500
```

Outputs:

```text
reports/ml-evaluation/wider-face/detection_summary.json
reports/ml-evaluation/wider-face/detection_sample.csv
```

This reports detected face counts per image and per WIDER event class. It is a baseline smoke test for crowd/event detection. Full WIDER recall evaluation needs official WIDER annotation parsing, which can be added later.

## Face Similarity: VGGFace2

Run a small smoke test:

```bash
apps/ml-service/.venv/bin/python scripts/evaluate_face_pipeline.py vggface2-verification --identities 5 --images-per-identity 3
```

Run a larger sample:

```bash
apps/ml-service/.venv/bin/python scripts/evaluate_face_pipeline.py vggface2-verification --identities 100 --images-per-identity 5
```

Outputs:

```text
reports/ml-evaluation/vggface2/verification_summary.json
reports/ml-evaluation/vggface2/verification_pairs.csv
reports/ml-evaluation/vggface2/skipped_images.json
```

The important signal is the separation between:

- `positive_similarity_average`: same identity pairs.
- `negative_similarity_average`: different identity pairs.
- `positive_similarity_min` and `negative_similarity_max`: threshold risk zone.

## Body Re-ID Manifests

Build body Re-ID manifests:

```bash
python3 scripts/build_body_reid_manifests.py
```

Outputs:

```text
reports/ml-evaluation/body-reid/market_1501_manifest.csv
reports/ml-evaluation/body-reid/deepsportradar_manifest.csv
```

These manifests are not training yet. They create clean metadata for future body-embedding experiments:

- Market-1501: identity and camera labels parsed from filenames.
- DeepSportradar: identity and camera labels parsed from the dataset folder/filename layout.

## Body Re-ID Baseline

Run a simple HSV color-histogram baseline:

```bash
apps/ml-service/.venv/bin/python scripts/evaluate_body_reid_baseline.py market-1501 --queries 100 --gallery-per-identity 4
apps/ml-service/.venv/bin/python scripts/evaluate_body_reid_baseline.py deepsportradar --identities 50
```

Outputs:

```text
reports/ml-evaluation/body-reid/baseline/market_1501_color_baseline_summary.json
reports/ml-evaluation/body-reid/baseline/market_1501_color_baseline.csv
reports/ml-evaluation/body-reid/baseline/deepsportradar_color_baseline_summary.json
reports/ml-evaluation/body-reid/baseline/deepsportradar_color_pairs.csv
```

This is deliberately a weak baseline. It only uses rough clothing/color layout, so a trained Re-ID model should beat it clearly. If a future deep model cannot beat this baseline, the training setup is wrong.

## Body Re-ID Pretrained Model

The next step is a real person Re-ID embedding model. This project includes an evaluator for `torchreid` OSNet:

```bash
scripts/evaluate_body_reid_torchreid.py
```

This requires a PyTorch environment. If your Homebrew Python is too new for PyTorch, install Python 3.12 first:

```bash
brew install python@3.12
```

Then create a separate environment:

```bash
cd /Users/hafeez/Workspaces/visora-platform
/opt/homebrew/bin/python3.12 -m venv .venv-reid
source .venv-reid/bin/activate
./scripts/install-reid-deps.sh
```

The helper clones `deep-person-reid` into `.external/deep-person-reid` and the evaluator loads it from there. This avoids pip metadata/build failures from installing the old research repo as a wheel.

Verify the install:

```bash
which python
python - <<'PY'
import torch
import torchreid
print("torch", torch.__version__)
print("torchreid loaded")
PY
```

Run a small OSNet evaluation:

```bash
source .venv-reid/bin/activate
python scripts/evaluate_body_reid_torchreid.py --queries 50 --gallery-per-identity 4 --device cpu
```

The evaluator ranks with Euclidean distance by default, matching the usual torchreid model-zoo evaluation. Use `--metric cosine` only when you explicitly want normalized cosine similarity.

Outputs:

```text
reports/ml-evaluation/body-reid/torchreid/market_1501_osnet_summary.json
reports/ml-evaluation/body-reid/torchreid/market_1501_osnet.csv
```

This should beat the HSV color baseline by a wide margin. If it does, we can later wire body embeddings into Visora as a second search layer.

### Use Market-1501 Trained Weights

The first run above uses ImageNet-pretrained OSNet unless trained Re-ID weights are present. Download the official torchreid OSNet Market-1501 model-zoo weight:

```bash
source .venv-reid/bin/activate
./scripts/download-osnet-market1501.sh
```

Then rerun:

```bash
python scripts/evaluate_body_reid_torchreid.py --queries 50 --gallery-per-identity 4 --device cpu
```

The evaluator automatically uses:

```text
apps/ml-service/models/body_reid/osnet_x1_0_market1501.pth.tar
```

when that file exists.

### Fine-Tune OSNet On Market-1501

To start a short CPU smoke training run:

```bash
source .venv-reid/bin/activate
MAX_EPOCH=1 EVAL_FREQ=1 ./scripts/train-body-reid-market1501.sh
```

For a longer R&D run:

```bash
source .venv-reid/bin/activate
MAX_EPOCH=60 EVAL_FREQ=10 ./scripts/train-body-reid-market1501.sh
```

Training outputs are written under:

```text
reports/ml-evaluation/body-reid/training/osnet_x1_0_market1501
```

## Training Decision

Train or fine-tune only after evaluation shows a weakness:

1. If WIDER FACE smoke tests miss too many event/crowd faces, improve detection first.
2. If VGGFace2 positive/negative scores overlap too much, tune face thresholds or evaluate stronger face embeddings.
3. If event photos often hide faces, add body Re-ID using Market-1501 and DeepSportradar as the first benchmark.
