from unittest import TestCase
from unittest.mock import patch

from fastapi.testclient import TestClient

from face_detection import DetectedFace, FaceBox, FaceDetectionError
from main import app


class MainTest(TestCase):
    def test_index_photo_returns_detected_faces(self) -> None:
        client = TestClient(app)

        detected_face = DetectedFace(
            box=FaceBox(1, 2, 30, 40),
            embedding=[0.0] * 128,
        )

        with (
            patch("main.detect_faces_with_embeddings", return_value=[detected_face]),
            patch("main.upsert_faces") as upsert_faces,
        ):
            response = client.post(
                "/index-photo",
                data={
                    "event_id": "event-1",
                    "photo_id": "photo-1",
                },
                files={
                    "photo": ("sample.jpg", b"fake-image", "image/jpeg"),
                },
            )

        self.assertEqual(200, response.status_code)
        self.assertEqual(
            {
                "status": "indexed",
                "event_id": "event-1",
                "photo_id": "photo-1",
                "filename": "sample.jpg",
                "faces_detected": 1,
                "faces": [
                    {
                        "x": 1,
                        "y": 2,
                        "width": 30,
                        "height": 40,
                    }
                ],
                "qdrant_status": "stored",
            },
            response.json(),
        )
        upsert_faces.assert_called_once()

    def test_invalid_image_returns_validation_error(self) -> None:
        client = TestClient(app)

        with patch(
            "main.detect_faces_with_embeddings",
            side_effect=FaceDetectionError("Uploaded image could not be decoded."),
        ):
            response = client.post(
                "/index-photo",
                data={
                    "event_id": "event-1",
                    "photo_id": "photo-1",
                },
                files={
                    "photo": ("sample.txt", b"not-an-image", "text/plain"),
                },
            )

        self.assertEqual(422, response.status_code)
        self.assertEqual(
            {"detail": "Uploaded image could not be decoded."},
            response.json(),
        )

    def test_search_selfie_returns_qdrant_matches(self) -> None:
        client = TestClient(app)
        detected_face = DetectedFace(
            box=FaceBox(1, 2, 30, 40),
            embedding=[0.0] * 128,
        )

        with (
            patch("main.detect_faces_with_embeddings", return_value=[detected_face]),
            patch("main.search_faces", return_value=["photo-1", "photo-2"]),
        ):
            response = client.post(
                "/search-selfie",
                data={
                    "event_id": "event-1",
                },
                files={
                    "selfie": ("selfie.jpg", b"fake-image", "image/jpeg"),
                },
            )

        self.assertEqual(200, response.status_code)
        self.assertEqual(
            {
                "status": "searched",
                "event_id": "event-1",
                "filename": "selfie.jpg",
                "faces_detected": 1,
                "matches": ["photo-1", "photo-2"],
            },
            response.json(),
        )
