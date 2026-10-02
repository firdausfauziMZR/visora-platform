# Visora R&D Datasets

This project keeps large datasets outside git under the local `datasets/` folder.

Current local datasets:

- `datasets/wider-face` - face detection stress testing for crowds and events.
- `datasets/lfw` - small face-recognition smoke-test dataset.

Recommended additional datasets:

| Dataset | Local folder | Purpose | Use in Visora |
| --- | --- | --- | --- |
| VGGFace2 | `datasets/vggface2` | Face identity similarity benchmark | Evaluate face embeddings and thresholds |
| Market-1501 | `datasets/market-1501` | Person/body re-identification benchmark | Prototype body-similarity search |
| DeepSportradar | `datasets/deepsportradar` | Sports player re-identification benchmark | R&D reference for sports body Re-ID |

Avoid using DukeMTMC-reID as a default project dataset. It is widely cited, but it has known privacy and dataset-stewardship concerns. If it is ever used, keep it as a literature comparison only and document the reason.

## Expected Folder Layout

```text
datasets/
├── wider-face/
│   ├── WIDER_train.zip
│   ├── WIDER_val.zip
│   ├── WIDER_test.zip
│   └── WIDER_train/
├── lfw/
├── vggface2/
│   ├── raw/
│   │   ├── train.tar.gz
│   │   └── test.tar.gz
│   ├── train/
│   └── test/
├── market-1501/
│   ├── raw/
│   │   └── Market-1501-v15.09.15.zip
│   └── Market-1501-v15.09.15/
└── deepsportradar/
    ├── raw/
    └── extracted/
```

## Setup Commands

Create local folders and inspect what is already available:

```bash
./scripts/prepare-datasets.sh
python3 scripts/dataset_inventory.py
```

After placing official archives in each `raw/` directory, run:

```bash
./scripts/prepare-datasets.sh --extract
python3 scripts/dataset_inventory.py
```

## Download Notes

### VGGFace2

Preferred official route: use the official VGGFace2 access process and terms. Place official archives in:

```text
datasets/vggface2/raw/train.tar.gz
datasets/vggface2/raw/test.tar.gz
```

KaggleHub route: if you choose to use the Kaggle-hosted mirror, install KaggleHub and run:

```bash
python3 -m pip install kagglehub
python3 scripts/download-vggface2-kaggle.py
python3 scripts/dataset_inventory.py
```

By default, this creates a symlink at:

```text
datasets/vggface2/kagglehub
```

To copy the files into the project dataset folder instead of symlinking KaggleHub's cache:

```bash
python3 scripts/download-vggface2-kaggle.py --copy
```

Use this for face similarity evaluation, not as customer production data.

### Market-1501

Place the archive in:

```text
datasets/market-1501/raw/Market-1501-v15.09.15.zip
```

Use this for body/person embedding experiments. It does not match marathon photography perfectly, but it is useful for baseline Re-ID metrics.

If using a Kaggle mirror instead, pass its Kaggle slug explicitly:

```bash
source .venv-datasets/bin/activate
python scripts/download-kaggle-dataset.py "OWNER/DATASET_SLUG" market-1501
python scripts/dataset_inventory.py
```

Replace `OWNER/DATASET_SLUG` with the exact Market-1501 Kaggle dataset slug you choose.

### DeepSportradar

Follow the official repository/challenge instructions and place downloaded archives under:

```text
datasets/deepsportradar/raw/
```

This is useful as a sports Re-ID reference, but it is basketball-oriented rather than road-running oriented.

If using a Kaggle mirror instead, pass its Kaggle slug explicitly:

```bash
source .venv-datasets/bin/activate
python scripts/download-kaggle-dataset.py "OWNER/DATASET_SLUG" deepsportradar
python scripts/dataset_inventory.py
```

Replace `OWNER/DATASET_SLUG` with the exact DeepSportradar Kaggle dataset slug you choose.

## Evaluation Strategy

Use datasets independently:

1. **WIDER FACE**: detection recall in crowds and difficult face scales.
2. **VGGFace2**: face embedding similarity and threshold calibration.
3. **Market-1501 / DeepSportradar**: body crop embedding experiments when faces are missing.
4. **Own event photos + admin-confirmed matches**: production validation and final tuning.

Do not merge face-detection, face-recognition, and body-ReID datasets into a single training split. They answer different questions.
