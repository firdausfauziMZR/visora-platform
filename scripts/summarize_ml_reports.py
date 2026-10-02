#!/usr/bin/env python3
from __future__ import annotations

import json
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
REPORTS = ROOT / "reports" / "ml-evaluation"


def load_json(path: Path) -> dict:
    if not path.exists():
        return {}

    return json.loads(path.read_text(encoding="utf-8"))


def main() -> int:
    wider = load_json(REPORTS / "wider-face" / "detection_summary.json")
    vggface2 = load_json(REPORTS / "vggface2" / "verification_summary.json")
    market = load_json(REPORTS / "body-reid" / "baseline" / "market_1501_color_baseline_summary.json")
    deepsport = load_json(REPORTS / "body-reid" / "baseline" / "deepsportradar_color_baseline_summary.json")

    print("Visora ML Evaluation Summary")
    print("=" * 28)

    if wider:
        print("\nWIDER FACE detection")
        print(f"- Sampled images: {wider.get('sampled_images')}")
        print(f"- Total faces detected: {wider.get('total_faces_detected')}")
        print(f"- Average faces/image: {wider.get('average_faces_per_image')}")
        print(f"- Failed images: {wider.get('failed_images')}")
    else:
        print("\nWIDER FACE detection: no report yet")

    if vggface2:
        print("\nVGGFace2 face similarity")
        print(f"- Embedded images: {vggface2.get('embedded_images')}")
        print(f"- Positive pairs: {vggface2.get('positive_pairs')}")
        print(f"- Negative pairs: {vggface2.get('negative_pairs')}")
        print(f"- Positive avg similarity: {vggface2.get('positive_similarity_average')}")
        print(f"- Negative avg similarity: {vggface2.get('negative_similarity_average')}")
    else:
        print("\nVGGFace2 face similarity: no report yet")

    if market:
        print("\nMarket-1501 body baseline")
        print(f"- Queries: {market.get('queries')}")
        print(f"- Gallery: {market.get('gallery')}")
        print(f"- Top-1 accuracy: {market.get('top1_accuracy')}")
        print(f"- Top-5 accuracy: {market.get('top5_accuracy')}")
    else:
        print("\nMarket-1501 body baseline: no report yet")

    if deepsport:
        print("\nDeepSportradar body baseline")
        print(f"- Sampled identities: {deepsport.get('sampled_identities')}")
        print(f"- Positive avg similarity: {deepsport.get('positive_similarity_average')}")
        print(f"- Negative avg similarity: {deepsport.get('negative_similarity_average')}")
    else:
        print("\nDeepSportradar body baseline: no report yet")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
