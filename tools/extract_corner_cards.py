#!/usr/bin/env python3
"""Render PDF pages and crop them between their four shared corner crosses."""

from __future__ import annotations

import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

import numpy as np
from PIL import Image


def ghostscript() -> str:
    for name in ("gswin64c", "gswin32c", "gs"):
        executable = shutil.which(name)
        if executable:
            return executable
    raise RuntimeError("Ghostscript is required (gswin64c, gswin32c, or gs)")


def projected_intersection(gray: np.ndarray) -> tuple[int, int]:
    """Find where the top and left crop-mark lines would intersect."""
    height, width = gray.shape
    band = max(8, min(height, width) // 25)
    dark = gray < 110

    # Only inspect the white outer edge: the vertical mark enters from the top,
    # and the horizontal mark enters from the left. Their coordinates define
    # the projected intersection even though the printed lines do not touch.
    column_scores = dark[:band, : width // 3].sum(axis=0)
    row_scores = dark[: height // 3, :band].sum(axis=1)
    x = int(column_scores.argmax())
    y = int(row_scores.argmax())
    if column_scores[x] < band // 2 or row_scores[y] < band // 2:
        raise RuntimeError("could not find the top-left cutting marks")
    return x, y


def crop_box(images: list[Image.Image]) -> tuple[int, int, int, int]:
    shared = np.asarray(images[0].convert("L"), dtype=np.uint8).copy()
    for image in images[1:]:
        np.maximum(shared, np.asarray(image.convert("L"), dtype=np.uint8), out=shared)
    height, width = shared.shape
    inset_x, inset_y = projected_intersection(shared)
    if inset_x * 2 >= width or inset_y * 2 >= height:
        raise RuntimeError("detected cutting-cross intersection produces an invalid crop")
    return inset_x, inset_y, width - inset_x, height - inset_y


def main() -> int:
    pdf, output = Path(sys.argv[1]), Path(sys.argv[2])
    output.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix="extract-cards-") as temporary:
        pattern = Path(temporary) / "page-%04d.png"
        result = subprocess.run(
            [ghostscript(), "-dSAFER", "-dBATCH", "-dNOPAUSE", "-sDEVICE=png16m",
             "-r300", f"-sOutputFile={pattern}", str(pdf)],
            capture_output=True, text=True,
        )
        if result.returncode:
            raise RuntimeError(result.stderr.strip() or "Ghostscript failed")
        page_paths = sorted(Path(temporary).glob("page-*.png"))
        if not page_paths:
            raise RuntimeError(f"no pages rendered from {pdf}")
        images = [Image.open(path).convert("RGB") for path in page_paths]
        box = crop_box(images)
        for number, image in enumerate(images, 1):
            image.crop(box).save(output / f"{number:02d}.jpg", quality=95, subsampling=0)
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (IndexError, RuntimeError) as error:
        print(f"error: {error}", file=sys.stderr)
        raise SystemExit(1)
