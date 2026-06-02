# output-markdown-table-image-artifact-currentbase

Session: `port-dev-markerpdf-output76-20260602T223633Z`
Base accepted HEAD: `ba26c84773f1060ee6d968d946c818afcf0a3c26`

## Source Truth

Upstream markerPDF is pinned in the lane manifest at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.

- `marker/output.py::save_markdown()` creates a per-document output folder, writes the converted Markdown, writes `_meta.json`, and saves each extracted image as a PNG artifact.
- `marker_app.py::markdown_insert_images()` scans the Markdown with the upstream image-link regex and replaces references whose target is present in the image map with runtime-only PNG data URI HTML.
- `marker/tables/table.py::format_tables()` formats recognized table cells to Markdown, inserts that Markdown as a `Table` block, and leaves output persistence to the downstream output writer.
- The locked `tabled-pdf` table dependency formats extracted cells as Markdown/CSV/HTML and reports cell row/column metadata; for this slice, the native boundary is the pipe-table Markdown artifact after table formatting, not model execution.

Primary source files/pages inspected:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/output.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker_app.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/tables/table.py
- https://github.com/VikParuchuri/tabled

## Implementation

`OutputWriter::saveMarkdownArtifactBoundary()` now returns a `markdown_table_image_artifact` review manifest. It parses saved GitHub-style pipe tables, finds Marker-compatible image references inside header/data cells, and records:

- table count, row count, data-row count, and per-table image reference counts;
- persisted versus missing table image targets;
- table-cell coordinates, column headings, alt text, optional title text, source filenames, artifact hashes, and runtime-preview embeddability;
- table-specific unreferenced image artifacts and expected runtime preview data URI count.

The parser intentionally follows the existing upstream-compatible Marker app image regex: targets with literal whitespace are not considered embeddable Marker image references. Unsafe punctuation and path traversal are still handled through the existing output filename sanitization and Markdown/metadata rewrite path.

`examples/wordpress-output-markdown-table-image-artifact-currentbase.php` models a WordPress import review screen where a recognized Markdown table contains saved image crops, one missing manual-review crop, and one non-table image artifact that remains review-only.

## Verification

PHP lint:

- `php -l lanes/markerpdf/src/OutputWriter.php`
  - `No syntax errors detected in lanes/markerpdf/src/OutputWriter.php`
- `php -l lanes/markerpdf/tests/OutputMarkdownTableImageArtifactCurrentBaseTest.php`
  - `No syntax errors detected in lanes/markerpdf/tests/OutputMarkdownTableImageArtifactCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-output-markdown-table-image-artifact-currentbase.php`
  - `No syntax errors detected in lanes/markerpdf/examples/wordpress-output-markdown-table-image-artifact-currentbase.php`

Focused new test:

`php tools/run-tests.php lanes/markerpdf/tests/OutputMarkdownTableImageArtifactCurrentBaseTest.php`

- PASS reports persisted and missing image artifacts inside markdown table cells
- PASS keeps markdown table image artifact accounting when runtime preview html is disabled
- Result: `1 test files, 71 assertions, 0 failures`

Adjacent output/table gate:

`php tools/run-tests.php lanes/markerpdf/tests/OutputMarkdownTableImageArtifactCurrentBaseTest.php lanes/markerpdf/tests/OutputArtifactPreviewMarkdownImageBundleCurrentBaseTest.php lanes/markerpdf/tests/OutputRuntimePreviewArtifactBoundaryCurrentBaseTest.php lanes/markerpdf/tests/OutputWriterTest.php lanes/markerpdf/tests/MarkdownImageEmbedderTest.php lanes/markerpdf/tests/TableFormatterTest.php`

- Result: `6 test files, 303 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-output-markdown-table-image-artifact-currentbase.php`

- Passed and emitted `table_count=1`, `table_image_reference_count=3`, `embedded_table_image_reference_count=2`, `missing_table_image_reference_count=1`, `expected_runtime_preview_table_data_uri_count=2`, `missing_table_image_targets=["missing-table-crop.png"]`, `unreferenced_table_image_artifacts=["7_image_0.png"]`, rewritten cover/detail table references, and all upstream-runtime execution flags false.

Hygiene:

- `git diff --check -- lanes/markerpdf`
  - passed with no output

Status delta:

- Behavior tests: `921 -> 923` pass / `0` fail.
- Focused assertions added in the new test file: `71`.

## Non-overlap

This does not repeat the accepted output artifact sanitizer, runtime preview artifact boundary, markdown image bundle accounting, table recognition/formatting/replacement, forced-OCR table geometry, image raster/filter/color-space previews, marker server output pagination/error artifacts, benchmark output bundles, or PDF parser/xref/security/annotation/font/metadata slices.

The bounded new behavior is table-cell image artifact accounting after Markdown table formatting and before runtime-only preview embedding.

## Dependency Closure

No new support component is needed. This reuses native PHP output writing, Markdown image rewriting, persisted image artifact hashing, metadata rewriting, `MarkdownImageEmbedder`, and the existing table Markdown formatting boundary. Full live markerPDF parity remains gated on Streamlit, pypdfium2/PDFium, PIL, pdftext, Python model workers, Surya/Torch model downloads, tabled-pdf model execution, Texify, FastAPI/Uvicorn, benchmark workflows, and external OCR/PDF helpers; this patch does not execute those dependencies.
