# output-markdown-artifact-sanitize-images-currentbase

Session: `port-dev-markerpdf-output56-20260602T211350Z`
Base accepted HEAD: `0e451709894623744c6f5d4ef8d1ef3a4870fcbb`

## Source Truth

- Upstream markerPDF pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/output.py::save_markdown()` creates the document subfolder, writes Markdown and `_meta.json`, then saves each image under that subfolder as PNG.
- `marker/images/save.py::get_image_filename()` produces deterministic safe image artifact names: `{page.pnum}_image_{image_idx}.png`, and `images_to_dict()` keys the image map with those generated names.

Source URLs inspected:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/output.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/save.py

## Implementation

`OutputWriter::saveMarkdown()` now preserves upstream safe generated names and sanitizes any supplied native image-map key back into a single local PNG artifact filename before joining paths. Unsafe path segments, non-PNG extensions, spaces, punctuation, and empty names are normalized, and colliding sanitized names receive numeric suffixes.

The same source-to-sanitized mapping is applied to:

- Markdown image references;
- WordPress/HTML `src`, `href`, and `alt` attributes;
- exact metadata strings and metadata array keys.

This prevents supplied image names such as `../WP-cover?.jpeg` from writing outside the output subfolder or leaking into saved Markdown/metadata while keeping upstream names such as `0_image_0.png` unchanged.

## Verification

Red-first focused check before implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/OutputWriterTest.php
```

Result before implementation: `1 test files, 15 assertions, 2 failures`.

Final focused checks:

```bash
php -l lanes/markerpdf/src/OutputWriter.php
php -l lanes/markerpdf/tests/OutputWriterTest.php
php -l lanes/markerpdf/examples/wordpress-output-artifact.php
php tools/run-tests.php lanes/markerpdf/tests/OutputWriterTest.php
php lanes/markerpdf/examples/wordpress-output-artifact.php
git diff --check -- lanes/markerpdf
```

Final focused test result: `1 test files, 25 assertions, 0 failures`.

WordPress smoke result: sanitized `3_image_0.png` exists, saved Markdown rewrites `src` and `alt` to `3_image_0.png`, and the traversal artifact outside the subfolder does not exist.

## Status Delta

- Behavior tests: `825 -> 827` pass / `0` fail.
- Focused assertions in `OutputWriterTest.php`: `13 -> 25`.
- Mapped upstream semantics remain `579 / 78`; this closes the existing output artifact boundary instead of adding a new upstream inventory row.

## Non-overlap

This does not repeat accepted image renderer/filter, inline image, metadata, xref, outline, annotation, AcroForm, or OCR/table slices. The bounded behavior is only output artifact filename sanitization and saved Markdown/metadata reference rewriting for supplied image payload keys.

## Dependency Closure

No new support component is needed. The slice reuses native PHP path/string handling, the existing `OutputWriter`, and upstream markerPDF's deterministic image artifact naming contract. Full live image raster parity remains gated on pypdfium2/PIL or a future native raster backend, but this patch does not execute Python, models, external PDF tools, pypdfium, or PIL.
