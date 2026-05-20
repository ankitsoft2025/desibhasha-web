import argparse
import base64
import json
import os
import urllib.error
import urllib.request
from typing import Dict, Optional


def _synthesize_request(url: str, payload: Dict[str, object]) -> str:
    req = urllib.request.Request(
        url=url,
        data=json.dumps(payload).encode("utf-8"),
        headers={"Content-Type": "application/json"},
        method="POST",
    )

    try:
        with urllib.request.urlopen(req, timeout=30) as response:
            return response.read().decode("utf-8")
    except urllib.error.HTTPError as exc:
        err_body = exc.read().decode("utf-8", errors="ignore")
        raise RuntimeError(f"Google TTS API error ({exc.code}): {err_body}") from exc


# Language and dialect configuration for future expansion.
LANGUAGE_CONFIG: Dict[str, Dict[str, object]] = {
    "hindi": {
        "language_code": "hi-IN",
        "dialects": ["hi-IN"],
    },
    "punjabi": {
        "language_code": "pa-IN",
        "dialects": ["pa-IN"],
    },
    "telugu": {
        "language_code": "te-IN",
        "dialects": ["te-IN"],
    },
    "marathi": {
        "language_code": "mr-IN",
        "dialects": ["mr-IN"],
    },
    "gujarati": {
        "language_code": "gu-IN",
        "dialects": ["gu-IN"],
    },
    "tamil": {
        "language_code": "ta-IN",
        "dialects": ["ta-IN"],
    },
    "malayalam": {
        "language_code": "ml-IN",
        "dialects": ["ml-IN"],
    },
    "kannada": {
        "language_code": "kn-IN",
        "dialects": ["kn-IN"],
    },
    "bangla": {
        "language_code": "bn-IN",
        "dialects": ["bn-IN"],
    },
    "odiya": {
        "language_code": "or-IN",
        "dialects": ["or-IN"],
    },
}

# Numeric ID → language key (matches language_id_to_name in the DB).
LANGUAGE_ID_MAP: Dict[str, str] = {
    "1": "hindi",
    "2": "punjabi",
    "3": "telugu",
    "4": "marathi",
    "5": "gujarati",
    "6": "tamil",
    "7": "malayalam",
    "8": "kannada",
    "9": "bangla",
    "10": "odiya",
}

# Accept common misspellings / alternate spellings as aliases.
LANGUAGE_ALIASES = {
    "hinfdi": "hindi",
    "hinddi": "hindi",
    "pubnjabi": "punjabi",
    "bengali": "bangla",
    "odia": "odiya",
    "oriya": "odiya",
}

GENDER_MAP = {
    "female": "FEMALE",
    "male": "MALE",
}

SUPPORTED_ENCODINGS = {"MP3", "OGG_OPUS", "LINEAR16"}


def _normalize_language(language: str) -> str:
    key = (language or "").strip().lower()
    key = LANGUAGE_ALIASES.get(key, key)
    if key not in LANGUAGE_CONFIG:
        supported = ", ".join(sorted(LANGUAGE_CONFIG.keys()))
        raise ValueError(f"Unsupported language '{language}'. Supported: {supported}")
    return key


def _resolve_dialect(language_key: str, dialect: str) -> str:
    configured_dialects = LANGUAGE_CONFIG[language_key]["dialects"]
    default_code = LANGUAGE_CONFIG[language_key]["language_code"]
    if not dialect:
        return str(default_code)

    dialect = dialect.strip()
    if dialect not in configured_dialects:
        allowed = ", ".join(configured_dialects)
        raise ValueError(f"Unsupported dialect '{dialect}' for {language_key}. Allowed: {allowed}")
    return dialect


def google_tts_save(
    text: str,
    language: str,
    dialect: str,
    gender: str,
    filename: str,
    api_key: str,
    audio_encoding: str = "MP3",
    sample_rate_hz: Optional[int] = None,
    folder_name: str = "audio_output",
) -> str:
    """Convert text to speech using Google Cloud TTS and save to MP3 file."""
    if not text or not text.strip():
        raise ValueError("Text cannot be empty.")
    if not filename or not filename.strip():
        raise ValueError("Filename cannot be empty.")
    if not api_key or not api_key.strip():
        raise ValueError("API key is required.")

    language_key = _normalize_language(language)
    language_code = _resolve_dialect(language_key, dialect)

    gender_key = (gender or "").strip().lower()
    if gender_key not in GENDER_MAP:
        raise ValueError("Gender must be 'female' or 'male'.")

    encoding = (audio_encoding or "").strip().upper()
    if encoding not in SUPPORTED_ENCODINGS:
        allowed = ", ".join(sorted(SUPPORTED_ENCODINGS))
        raise ValueError(f"Unsupported audio encoding '{audio_encoding}'. Allowed: {allowed}")
    if sample_rate_hz is not None and sample_rate_hz <= 0:
        raise ValueError("sample_rate_hz must be a positive integer.")

    url = f"https://texttospeech.googleapis.com/v1/text:synthesize?key={api_key.strip()}"
    payload = {
        "input": {"text": text.strip()},
        "voice": {
            "languageCode": language_code,
            "ssmlGender": GENDER_MAP[gender_key],
        },
        "audioConfig": {
            "audioEncoding": encoding,
        },
    }
    if sample_rate_hz is not None:
        payload["audioConfig"]["sampleRateHertz"] = sample_rate_hz

    try:
        body = _synthesize_request(url, payload)
    except RuntimeError as first_error:
        first_error_text = str(first_error).lower()
        retry_without_gender = (
            "google tts api error (400)" in first_error_text
            and "voice '' does not exist" in first_error_text
        )
        if not retry_without_gender:
            raise

        fallback_payload = {
            "input": payload["input"],
            "voice": {
                "languageCode": language_code,
            },
            "audioConfig": dict(payload["audioConfig"]),
        }
        body = _synthesize_request(url, fallback_payload)

    data = json.loads(body)
    audio_b64 = data.get("audioContent")
    if not audio_b64:
        raise RuntimeError(f"No audioContent returned by API: {body}")

    output_dir = os.path.join(os.path.dirname(__file__), folder_name)
    os.makedirs(output_dir, exist_ok=True)
    output_path = os.path.join(output_dir, os.path.basename(filename))

    audio_bytes = base64.b64decode(audio_b64)
    with open(output_path, "wb") as f:
        f.write(audio_bytes)

    return output_path


def main() -> None:
    parser = argparse.ArgumentParser(description="Google TTS saver (Hindi first, extensible config).")
    parser.add_argument(
        "--text",
        # default="अ से अनार !",
        default="ଡାଳିମ୍ବ",
        help="Text to convert to speech",
    )
    parser.add_argument("--language", default="oriya", help="hindi | punjabi | gujarati")
    parser.add_argument(
        "--dialect",
        default="",
        help="Optional dialect/language code (e.g., hi-IN). If omitted, default for selected language is used.",
    )
    parser.add_argument("--gender", default="female", help="female or male")
    parser.add_argument("--filename", default="hindi_output.mp3", help="Output MP3 file")
    parser.add_argument(
        "--audio-encoding",
        default="MP3",
        help="MP3 | OGG_OPUS | LINEAR16 (OGG_OPUS is usually smaller than MP3)",
    )
    parser.add_argument(
        "--sample-rate-hz",
        type=int,
        default=0,
        help="Optional sample rate in Hz for additional size control (example: 16000)",
    )
    parser.add_argument(
        "--api-key",
        default="AIzaSyD1BhlgTGTS56SWaiM0avAhvudxxFq77R4",
        help="Google API key (or set GOOGLE_TTS_API_KEY env var)",
    )

    args = parser.parse_args()

    api_key = os.getenv("GOOGLE_TTS_API_KEY", args.api_key)
    output = google_tts_save(
        text=args.text,
        language=args.language,
        dialect=args.dialect,
        gender=args.gender,
        filename=args.filename,
        api_key=api_key,
        audio_encoding=args.audio_encoding,
        sample_rate_hz=args.sample_rate_hz or None,
    )
    print(f"Saved audio: {output}")


if __name__ == "__main__":
    main()
