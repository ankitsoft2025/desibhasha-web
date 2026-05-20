"""
Batch PDF watermark utility.

Traverses an input directory recursively, applies a text watermark to all pages
of every PDF file found, and writes results to an output directory while
preserving the same subfolder structure.

Dependencies:
- pypdf
- reportlab

Example:
    python pdf_watermark_batch.py --input input --output output --text "Desi Bhasha" --color "#808080"
"""

from __future__ import annotations

import argparse
import io
import os
from pathlib import Path
from typing import Iterable, Tuple

from pypdf import PdfReader, PdfWriter
from reportlab.lib import colors
from reportlab.pdfgen import canvas


DEFAULT_TEXT = "Desi Bhasha"
DEFAULT_COLOR = "#808080"
DEFAULT_ALPHA = 0.18
DEFAULT_ORIENTATION = "diagonal"


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Recursively watermark PDF files while preserving folder structure."
    )
    parser.add_argument("--input", default="input", help="Input directory (default: input)")
    parser.add_argument("--output", default="output", help="Output directory (default: output)")
    parser.add_argument(
        "--text",
        default=DEFAULT_TEXT,
        help=f"Watermark text (default: {DEFAULT_TEXT!r})",
    )
    parser.add_argument(
        "--color",
        default=DEFAULT_COLOR,
        help=(
            "Watermark color as named color (e.g. gray), hex (#RRGGBB), "
            "or RGB triplet (e.g. 128,128,128)."
        ),
    )
    parser.add_argument(
        "--alpha",
        type=float,
        default=DEFAULT_ALPHA,
        help=f"Watermark opacity from 0.0 to 1.0 (default: {DEFAULT_ALPHA})",
    )
    parser.add_argument(
        "--orientation",
        choices=["diagonal", "horizontal", "vertical"],
        default=DEFAULT_ORIENTATION,
        help="Watermark orientation (default: diagonal)",
    )
    parser.add_argument(
        "--angle",
        type=float,
        default=None,
        help=(
            "Optional explicit rotation angle in degrees. "
            "Overrides --orientation when provided."
        ),
    )
    return parser.parse_args()


def _iter_pdf_files(root: Path) -> Iterable[Path]:
    for current_root, _, files in os.walk(root):
        current_root_path = Path(current_root)
        for file_name in files:
            if file_name.lower().endswith(".pdf"):
                yield current_root_path / file_name


def _has_pdf_signature(file_path: Path) -> bool:
    # Real PDF files start with the magic bytes: %PDF-
    try:
        with file_path.open("rb") as file_handle:
            return file_handle.read(5) == b"%PDF-"
    except OSError:
        return False


def _parse_color(value: str):
    value = value.strip()

    if value.startswith("#"):
        return colors.HexColor(value)

    if "," in value:
        parts = [part.strip() for part in value.split(",")]
        if len(parts) != 3:
            raise ValueError("RGB color must have exactly 3 components: r,g,b")
        r, g, b = (int(part) for part in parts)
        if any(channel < 0 or channel > 255 for channel in (r, g, b)):
            raise ValueError("RGB channel values must be in range 0-255")
        return colors.Color(r / 255.0, g / 255.0, b / 255.0)

    return colors.toColor(value)


def _resolve_angle(orientation: str, explicit_angle: float | None) -> float:
    if explicit_angle is not None:
        return explicit_angle

    mapping = {
        "diagonal": 45.0,
        "horizontal": 0.0,
        "vertical": 90.0,
    }
    return mapping[orientation]


def _create_watermark_page(
    page_width: float,
    page_height: float,
    text: str,
    color,
    alpha: float,
    angle: float,
):
    packet = io.BytesIO()
    cnv = canvas.Canvas(packet, pagesize=(page_width, page_height))

    font_size = max(24, int(min(page_width, page_height) * 0.11))

    cnv.saveState()
    cnv.translate(page_width / 2.0, page_height / 2.0)
    cnv.rotate(angle)

    try:
        cnv.setFillAlpha(alpha)
    except Exception:
        pass

    cnv.setFillColor(color)
    cnv.setFont("Helvetica-Bold", font_size)
    cnv.drawCentredString(0, 0, text)
    cnv.restoreState()

    cnv.save()
    packet.seek(0)

    overlay_reader = PdfReader(packet)
    return overlay_reader.pages[0]


def watermark_pdf(
    source_pdf: Path,
    destination_pdf: Path,
    text: str,
    color,
    alpha: float,
    angle: float,
) -> Tuple[int, Path]:
    reader = PdfReader(str(source_pdf))
    writer = PdfWriter()

    for page in reader.pages:
        width = float(page.mediabox.width)
        height = float(page.mediabox.height)
        watermark_page = _create_watermark_page(width, height, text, color, alpha, angle)
        page.merge_page(watermark_page)
        writer.add_page(page)

    destination_pdf.parent.mkdir(parents=True, exist_ok=True)
    with destination_pdf.open("wb") as file_handle:
        writer.write(file_handle)

    return len(reader.pages), destination_pdf


def main() -> None:
    args = parse_args()

    input_root = Path(args.input).resolve()
    output_root = Path(args.output).resolve()

    if not input_root.exists() or not input_root.is_dir():
        raise FileNotFoundError(f"Input directory not found: {input_root}")

    if not (0.0 <= args.alpha <= 1.0):
        raise ValueError("--alpha must be between 0.0 and 1.0")

    parsed_color = _parse_color(args.color)
    resolved_angle = _resolve_angle(args.orientation, args.angle)

    pdf_files = list(_iter_pdf_files(input_root))
    if not pdf_files:
        print(f"No PDF files found in: {input_root}")
        return

    processed_count = 0
    for source_pdf in pdf_files:
        if not _has_pdf_signature(source_pdf):
            print(f"Skipped (not a valid PDF signature): {source_pdf}")
            continue

        relative_path = source_pdf.relative_to(input_root)
        destination_pdf = output_root / relative_path

        page_count, output_path = watermark_pdf(
            source_pdf=source_pdf,
            destination_pdf=destination_pdf,
            text=args.text,
            color=parsed_color,
            alpha=args.alpha,
            angle=resolved_angle,
        )
        processed_count += 1
        print(f"Processed: {source_pdf} -> {output_path} ({page_count} pages)")

    print(f"Done. Watermarked {processed_count} PDF file(s).")


if __name__ == "__main__":
    main()
