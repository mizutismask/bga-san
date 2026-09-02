#!/usr/bin/env python3
"""Clip every page of a diecut+artwork PDF to its magenta cut line.

Open-source dependencies:
- Ghostscript for overprint-aware PDF rendering
- Pillow for PNG output
- NumPy for mask operations

Example:
    python tools/clip_pdf_diecut.py artwork.pdf clipped-pages
    python tools/clip_pdf_diecut.py pdf-folder

Parameters:
- input: A source PDF, or a folder whose PDF files should all be processed.
- output_dir: Optional folder where clipped PNG files are written. It is created
  if needed. Defaults to the input folder (or a PDF's parent folder).
- --dpi: Render resolution. Higher values produce larger/sharper PNGs and take
  more memory/time. Default 300 is print-quality.
- --barrier-radius: Expands the detected shared cutline before flood-filling the
  outside. Increase if tiny gaps in the magenta line let the outside leak into
  the card; decrease if the retained shape gets visibly too small.
- --erase-radius: Expands the cutline pixels that are made transparent. Increase
  if pink edge pixels remain; decrease if too much artwork disappears near the
  cut edge.
- --vote-threshold: Number of pages that must contain a magenta pixel at the same
  position for that pixel to be considered part of the shared cutline. The default
  is 60% of pages, which rejects page-specific pink artwork while keeping the
  common punchboard line.
- --cutline-output: Optional debug PNG path for the extracted shared cutline. By
  default no debug cutline image is written.
- --prefix: Output filename prefix. By default, the input filename stem is used.
  Use --prefix custom for custom_01.png. Use --prefix with no value, or
  --no-prefix, for 01.png, 02.png, etc.
- --no-prefix: Convenience flag for numbered-only filenames.
"""

from __future__ import annotations

import argparse
from collections import deque
from pathlib import Path
import shutil
import subprocess
import tempfile

import numpy as np
from PIL import Image


def detect_magenta_candidates(rgb: np.ndarray) -> np.ndarray:
    # Loose magenta threshold: catches anti-aliased diecut pixels, but may also
    # catch pink artwork. Later voting across all pages removes page-specific art.
    r = rgb[:, :, 0].astype(np.int16)
    g = rgb[:, :, 1].astype(np.int16)
    b = rgb[:, :, 2].astype(np.int16)

    return (r >= 125) & (b >= 105) & (g <= 125) & ((r - g) >= 35) & ((b - g) >= 25)


def detect_magenta_line(rgb: np.ndarray) -> np.ndarray:
    # Per-page stricter detector retained for diagnostics/fallback-style cleanup.
    # The main pipeline uses detect_magenta_candidates() plus cross-page voting.
    r = rgb[:, :, 0].astype(np.int16)
    g = rgb[:, :, 1].astype(np.int16)
    b = rgb[:, :, 2].astype(np.int16)

    strong = (r >= 185) & (b >= 145) & (g <= 95) & ((r - g) >= 100) & ((b - g) >= 70)
    return keep_components_touching_strong(detect_magenta_candidates(rgb), strong)


def keep_components_touching_strong(loose: np.ndarray, strong: np.ndarray) -> np.ndarray:
    height, width = loose.shape
    seen = np.zeros_like(loose, dtype=bool)
    kept = np.zeros_like(loose, dtype=bool)

    for start_y, start_x in zip(*np.where(loose & ~seen)):
        pixels: list[tuple[int, int]] = []
        has_strong = False
        queue: deque[tuple[int, int]] = deque([(int(start_y), int(start_x))])
        seen[start_y, start_x] = True

        while queue:
            y, x = queue.popleft()
            pixels.append((y, x))
            has_strong = has_strong or bool(strong[y, x])

            for ny, nx in ((y - 1, x), (y + 1, x), (y, x - 1), (y, x + 1)):
                if 0 <= ny < height and 0 <= nx < width and loose[ny, nx] and not seen[ny, nx]:
                    seen[ny, nx] = True
                    queue.append((ny, nx))

        if has_strong:
            for y, x in pixels:
                kept[y, x] = True

    return kept


def dilate(mask: np.ndarray, radius: int) -> np.ndarray:
    if radius <= 0:
        return mask.copy()

    height, width = mask.shape
    out = np.zeros_like(mask, dtype=bool)
    ys, xs = np.where(mask)
    for y, x in zip(ys, xs):
        y0 = max(0, int(y) - radius)
        y1 = min(height, int(y) + radius + 1)
        x0 = max(0, int(x) - radius)
        x1 = min(width, int(x) + radius + 1)
        out[y0:y1, x0:x1] = True
    return out


def flood_outside(barrier: np.ndarray) -> np.ndarray:
    height, width = barrier.shape
    outside = np.zeros_like(barrier, dtype=bool)
    queue: deque[tuple[int, int]] = deque()

    def push(y: int, x: int) -> None:
        if not barrier[y, x] and not outside[y, x]:
            outside[y, x] = True
            queue.append((y, x))

    for x in range(width):
        push(0, x)
        push(height - 1, x)
    for y in range(height):
        push(y, 0)
        push(y, width - 1)

    while queue:
        y, x = queue.popleft()
        if y > 0:
            push(y - 1, x)
        if y < height - 1:
            push(y + 1, x)
        if x > 0:
            push(y, x - 1)
        if x < width - 1:
            push(y, x + 1)

    return outside


def crop_visible(rgba: np.ndarray) -> np.ndarray:
    alpha = rgba[:, :, 3]
    ys, xs = np.where(alpha > 0)
    if len(xs) == 0:
        return rgba

    return rgba[int(ys.min()) : int(ys.max()) + 1, int(xs.min()) : int(xs.max()) + 1, :]


def save_cutline_preview(cutline: np.ndarray, output: Path) -> None:
    rgba = np.zeros((*cutline.shape, 4), dtype=np.uint8)
    rgba[cutline, 0] = 255
    rgba[cutline, 2] = 255
    rgba[cutline, 3] = 255
    Image.fromarray(crop_visible(rgba), mode="RGBA").save(output)


def find_ghostscript() -> str:
    for executable in ("gswin64c", "gswin32c", "gs"):
        path = shutil.which(executable)
        if path:
            return path
    raise ValueError("Ghostscript is required: install it and add gswin64c (Windows) or gs to PATH")


def render_pdf(pdf: Path, dpi: int) -> list[Image.Image]:
    with tempfile.TemporaryDirectory(prefix="clip-pdf-") as temporary_dir:
        output_pattern = Path(temporary_dir) / "page-%04d.png"
        command = [
            find_ghostscript(),
            "-dSAFER",
            "-dBATCH",
            "-dNOPAUSE",
            "-dOverprint=/simulate",
            "-sDEVICE=png16m",
            "-dTextAlphaBits=4",
            "-dGraphicsAlphaBits=4",
            f"-r{dpi}",
            f"-sOutputFile={output_pattern}",
            str(pdf),
        ]
        result = subprocess.run(command, capture_output=True, text=True)
        if result.returncode != 0:
            details = result.stderr.strip() or result.stdout.strip() or f"exit code {result.returncode}"
            raise ValueError(f"Ghostscript failed to render {pdf}: {details}")

        pages = sorted(Path(temporary_dir).glob("page-*.png"))
        if not pages:
            raise ValueError(f"Ghostscript rendered no pages from {pdf}")
        return [Image.open(page).convert("RGBA") for page in pages]


def build_common_cutline(images: list[Image.Image], vote_threshold: int) -> np.ndarray:
    # The diecut is printed in the same position on every page. Pink artwork is
    # not, so a per-pixel vote isolates the shared punchboard cutline.
    votes: np.ndarray | None = None

    for image in images:
        rgb = np.array(image, dtype=np.uint8)[:, :, :3]
        candidates = detect_magenta_candidates(rgb)
        if votes is None:
            votes = np.zeros(candidates.shape, dtype=np.uint16)
        votes += candidates

    if votes is None:
        raise ValueError("no pages were rendered")

    line = votes >= vote_threshold
    if not line.any():
        raise ValueError(f"no common magenta cutline was detected with vote threshold {vote_threshold}")
    return line


def clip_page(
    image: Image.Image,
    cutline: np.ndarray,
    barrier_radius: int,
    erase_radius: int,
) -> tuple[Image.Image, dict[str, int]]:
    rgba = np.array(image, dtype=np.uint8)

    # Dilation closes small rasterization gaps so flood-fill cannot pass through
    # the line. The erase dilation is separate because it controls visible edge
    # cleanup rather than inside/outside classification.
    barrier = dilate(cutline, barrier_radius)
    outside = flood_outside(barrier)
    remove = outside | dilate(cutline, erase_radius)

    rgba[remove, 0] = 0
    rgba[remove, 1] = 0
    rgba[remove, 2] = 0
    rgba[remove, 3] = 0

    cropped = crop_visible(rgba)
    stats = {
        "line_pixels": int(cutline.sum()),
        "transparent_pixels": int(remove.sum()),
        "width": int(cropped.shape[1]),
        "height": int(cropped.shape[0]),
    }
    return Image.fromarray(cropped, mode="RGBA"), stats


def process_pdf(
    pdf: Path,
    output_dir: Path,
    dpi: int,
    barrier_radius: int,
    erase_radius: int,
    requested_vote_threshold: int,
    prefix: str | None,
    cutline_output: Path | None,
) -> None:
    print(f"{pdf}: rendering with Ghostscript overprint simulation at {dpi} DPI")
    images = render_pdf(pdf, dpi)
    page_count = len(images)
    print(f"Rendered {page_count} page(s)")
    vote_threshold = requested_vote_threshold or max(1, round(page_count * 0.6))
    if vote_threshold > page_count:
        raise ValueError(f"vote threshold {vote_threshold} exceeds page count {page_count} for {pdf}")

    cutline = build_common_cutline(images, vote_threshold)
    print(f"Common cutline: {int(cutline.sum())} px with vote threshold {vote_threshold}")
    if cutline_output:
        cutline_output.parent.mkdir(parents=True, exist_ok=True)
        save_cutline_preview(cutline, cutline_output)
        print(f"Cutline: {cutline_output}")

    output_prefix = pdf.stem if prefix is None else prefix
    for page_index, image in enumerate(images):
        clipped, stats = clip_page(image, cutline, barrier_radius, erase_radius)
        filename = f"{output_prefix}_{page_index + 1:02d}.png" if output_prefix else f"{page_index + 1:02d}.png"
        output = output_dir / filename
        clipped.save(output)
        print(
            f"{page_index + 1:02d}: {output} "
            f"({stats['width']}x{stats['height']}, "
            f"{stats['line_pixels']} line px, {stats['transparent_pixels']} transparent px)"
        )


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("input", type=Path, help="Input PDF, or directory containing PDFs")
    parser.add_argument("output_dir", type=Path, nargs="?", help="Directory for clipped PNG pages (default: input directory)")
    parser.add_argument("--dpi", type=int, default=300, help="PDF render DPI")
    parser.add_argument("--barrier-radius", type=int, default=4, help="Pixels to close gaps in the cut line")
    parser.add_argument("--erase-radius", type=int, default=5, help="Pixels around the cut line to make transparent")
    parser.add_argument(
        "--vote-threshold",
        type=int,
        default=0,
        help="Pages that must contain a magenta pixel for it to be part of the common cutline. Defaults to 60%%.",
    )
    parser.add_argument("--cutline-output", type=Path, default=None, help="Optional PNG path for the extracted common cutline.")
    parser.add_argument(
        "--prefix",
        nargs="?",
        const="",
        default=None,
        help="Output PNG filename prefix (default: input filename stem). Use --prefix with no value, or --no-prefix, for just numbers.",
    )
    parser.add_argument("--no-prefix", action="store_true", help="Write page PNGs as 01.png, 02.png, etc.")
    args = parser.parse_args()
    if args.dpi <= 0:
        parser.error("--dpi must be greater than zero")
    if args.barrier_radius < 0 or args.erase_radius < 0:
        parser.error("--barrier-radius and --erase-radius cannot be negative")
    if args.vote_threshold < 0:
        parser.error("--vote-threshold cannot be negative")
    if not args.input.exists():
        parser.error(f"input does not exist: {args.input}")
    if not args.input.is_file() and not args.input.is_dir():
        parser.error(f"input is neither a file nor a directory: {args.input}")

    if args.input.is_dir():
        pdfs = sorted(
            (path for path in args.input.iterdir() if path.is_file() and path.suffix.lower() == ".pdf"),
            key=lambda path: path.name.lower(),
        )
        if not pdfs:
            parser.error(f"input directory contains no PDF files: {args.input}")
    else:
        if args.input.suffix.lower() != ".pdf":
            parser.error(f"input file is not a PDF: {args.input}")
        pdfs = [args.input]

    if args.no_prefix:
        args.prefix = ""
    if len(pdfs) > 1 and args.prefix is not None:
        parser.error("--prefix and --no-prefix require a single input PDF to avoid overwriting output files")
    if len(pdfs) > 1 and args.cutline_output is not None:
        parser.error("--cutline-output requires a single input PDF")

    output_dir = args.output_dir or (args.input if args.input.is_dir() else args.input.parent)
    output_dir.mkdir(parents=True, exist_ok=True)

    for pdf in pdfs:
        try:
            process_pdf(
                pdf,
                output_dir,
                args.dpi,
                args.barrier_radius,
                args.erase_radius,
                args.vote_threshold,
                args.prefix,
                args.cutline_output,
            )
        except ValueError as error:
            parser.error(str(error))

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
