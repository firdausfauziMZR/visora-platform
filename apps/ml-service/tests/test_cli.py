import json
from pathlib import Path
from tempfile import TemporaryDirectory
from unittest import TestCase
from unittest.mock import patch

from face_detection import DetectedFace, FaceBox
from visora_ml_cli import main


class CliTest(TestCase):
    def test_index_photo_writes_qdrant_payload_to_stdout(self) -> None:
        detected_face = DetectedFace(
            box=FaceBox(1, 2, 30, 40),
            embedding=[0.0] * 128,
        )

        with TemporaryDirectory() as temp_dir:
            image_path = Path(temp_dir) / "sample.jpg"
            image_path.write_bytes(b"fake-image")

            with (
                patch(
                    "sys.argv",
                    [
                        "visora_ml_cli.py",
                        "index-photo",
                        "--event-id",
                        "event-1",
                        "--photo-id",
                        "photo-1",
                        "--photo-path",
                        str(image_path),
                    ],
                ),
                patch("visora_ml_cli.detect_faces_with_embeddings", return_value=[detected_face]),
                patch("builtins.print") as print_mock,
            ):
                exit_code = main()

        self.assertEqual(0, exit_code)

        payload = json.loads(print_mock.call_args.args[0])
        self.assertEqual(
            {
                "status": "indexed",
                "event_id": "event-1",
                "photo_id": "photo-1",
                "filename": "sample.jpg",
                "faces_detected": 1,
                "faces": [
                    {
                        "box": {
                            "x": 1,
                            "y": 2,
                            "width": 30,
                            "height": 40,
                        },
                        "embedding": [0.0] * 128,
                    }
                ],
                "embedding_status": "generated",
            },
            payload,
        )
