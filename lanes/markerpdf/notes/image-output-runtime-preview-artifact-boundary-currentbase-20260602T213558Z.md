# markerPDF Image Output Runtime Preview Artifact Boundary

Micro-slice: `image-output-runtime-preview-artifact-boundary-currentbase`

Session: `port-dev-markerpdf-image63-20260602T213558Z`

Base accepted HEAD: `99591cbc6337f72bc79127211674461d42c783cc`

## Source Truth

Upstream `sddai/markerPDF` is pinned in the lane manifest at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.

- `marker/output.py::save_markdown` creates the per-document output folder, writes Markdown, writes the sibling `_meta.json`, and saves each image as a PNG artifact.
- `marker_app.py::markdown_insert_images` is a runtime preview path: it replaces Markdown image spans with PNG data URI HTML for Streamlit display while missing image references remain unchanged.
- `convert.py` and `convert_single.py` pass the conversion tuple `(full_text, images, out_meta)` into `save_markdown`; this slice keeps the same artifact boundary without launching Python workers.

Primary upstream files inspected:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/output.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker_app.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert_single.py`

## Patch

- Added `OutputWriter::saveMarkdownArtifactBoundary()` as an additive wrapper around the existing `saveMarkdown()` path.
- The existing upstream-compatible `saveMarkdown()` return contract remains unchanged.
- The new boundary returns a manifest for:
  - persisted Markdown artifact;
  - persisted review-only `_meta.json`;
  - sanitized PNG image artifacts with size/hash/path metadata;
  - source-to-sanitized image filename rewrites;
  - runtime-only marker_app-style preview HTML generated from persisted image bytes;
  - missing preview image links preserved as reviewable Markdown links;
  - explicit non-execution flags for Streamlit, PDFium, Python/models, and external PDF tools.
- Added `OutputRuntimePreviewArtifactBoundaryCurrentBaseTest.php`.
- Added `wordpress-marker-runtime-preview-artifact-boundary-currentbase.php`.
- Updated `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` for `phpPass 855 -> 857` and mapped semantics `601 -> 602 / 78`.

## Verification

PHP lint:

- `php -l lanes/markerpdf/src/OutputWriter.php`
  - `No syntax errors detected in lanes/markerpdf/src/OutputWriter.php`
- `php -l lanes/markerpdf/tests/OutputRuntimePreviewArtifactBoundaryCurrentBaseTest.php`
  - `No syntax errors detected in lanes/markerpdf/tests/OutputRuntimePreviewArtifactBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-preview-artifact-boundary-currentbase.php`
  - `No syntax errors detected in lanes/markerpdf/examples/wordpress-marker-runtime-preview-artifact-boundary-currentbase.php`

Focused new test:

`php tools/run-tests.php lanes/markerpdf/tests/OutputRuntimePreviewArtifactBoundaryCurrentBaseTest.php`

- PASS persists output artifacts and exposes runtime-only preview html without leaking image payloads
- PASS can report saved output artifacts without building a runtime preview html payload
- Result: `1 test files, 66 assertions, 0 failures`

Focused output/runtime family:

`php tools/run-tests.php lanes/markerpdf/tests/OutputRuntimePreviewArtifactBoundaryCurrentBaseTest.php lanes/markerpdf/tests/OutputWriterTest.php lanes/markerpdf/tests/MarkdownImageEmbedderTest.php lanes/markerpdf/tests/SingleDocumentConverterTest.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php`

- Result: `6 test files, 259 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preview-artifact-boundary-currentbase.php`

- Passed and emitted `sanitized_image="wp_preview.png"`, `runtime_preview_only=true`, `runtime_preview_persisted=false`, `runtime_preview_data_uri_count=1`, `missing_preview_link_preserved=true`, `markdown_keeps_file_references=true`, and all execution flags false.

JSON/hygiene:

- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'`
  - `markerpdf json ok`
- `git diff --check -- lanes/markerpdf`
  - passed with no output

## Dependency Closure

No new support component is needed. This reuses native PHP output writing, image filename sanitization, metadata rewriting, file hashing, and `MarkdownImageEmbedder` preview HTML generation. Full upstream runtime parity remains gated on Streamlit, pypdfium2/PDFium, PIL, Python model workers, pdftext, Surya/Torch models, tabled-pdf, Texify, FastAPI/Uvicorn, benchmark workflows, and external OCR/PDF helper tooling.

## Non-Overlap

This does not repeat accepted image raster/parser color-space slices, PDF parser/xref/security/annotation/font/table/metadata behavior, benchmark error artifacts, batch progress resume, marker app config, runtime conversion pool planning, server error boundaries, or the existing basic output artifact sanitizer. The bounded behavior is the current-base boundary that describes how saved output artifacts feed runtime-only marker_app image previews while persisted WordPress Markdown and review metadata remain separate from data URI preview payloads.
