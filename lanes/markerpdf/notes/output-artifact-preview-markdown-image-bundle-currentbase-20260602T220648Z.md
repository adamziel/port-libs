# output-artifact-preview-markdown-image-bundle-currentbase

Session: `port-dev-markerpdf-output71-20260602T220648Z`
Base accepted HEAD: `f7360ce6eb81b8c1919c66db722cb7028bf7e306`

## Source Truth

Upstream markerPDF is pinned in the lane manifest at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.

- `marker/output.py::save_markdown()` creates a per-document output folder, writes Markdown, writes `_meta.json`, and saves each image as a PNG artifact.
- `marker/images/save.py::images_to_dict()` keys extracted images by deterministic page-indexed PNG filenames from `get_image_filename()`.
- `marker_app.py::markdown_insert_images()` separately replaces Markdown image references with PNG data URI HTML for the Streamlit runtime preview while preserving missing image references.

Primary upstream files inspected:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/output.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/save.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker_app.py

## Implementation

`OutputWriter::saveMarkdownArtifactBoundary()` now includes a `markdown_image_bundle` review manifest that ties saved Markdown image targets to persisted PNG artifacts and runtime-only preview embedding. The bundle records artifact rows, target counts, embedded target counts, missing Markdown image targets, unreferenced saved image artifacts, and whether the data URI preview count matches embeddable references.

`OutputWriter::rewriteImageReferences()` now also rewrites upstream-compatible Markdown image links that carry optional title text, for example `![Cover](../cover.jpeg "source crop")`, preserving the title while replacing the target with the sanitized persisted PNG filename. This keeps saved WordPress Markdown, `_meta.json`, and marker_app-style runtime preview HTML aligned when native callers supply unsafe or colliding image keys.

`examples/wordpress-output-artifact-preview-markdown-image-bundle-currentbase.php` models a WordPress import review screen where two title-bearing Markdown image references embed in the runtime preview, one missing crop remains reviewable, and one persisted image artifact remains review-only because it is not referenced by Markdown.

## Verification

PHP lint:

- `php -l lanes/markerpdf/src/OutputWriter.php`
  - `No syntax errors detected in lanes/markerpdf/src/OutputWriter.php`
- `php -l lanes/markerpdf/tests/OutputArtifactPreviewMarkdownImageBundleCurrentBaseTest.php`
  - `No syntax errors detected in lanes/markerpdf/tests/OutputArtifactPreviewMarkdownImageBundleCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-output-artifact-preview-markdown-image-bundle-currentbase.php`
  - `No syntax errors detected in lanes/markerpdf/examples/wordpress-output-artifact-preview-markdown-image-bundle-currentbase.php`

Focused new test:

`php tools/run-tests.php lanes/markerpdf/tests/OutputArtifactPreviewMarkdownImageBundleCurrentBaseTest.php`

- PASS bundles saved markdown image artifacts with optional-title runtime preview targets
- PASS reports bundle accounting even when runtime preview html is not requested
- Result: `1 test files, 88 assertions, 0 failures`

Adjacent output/runtime gate:

`php tools/run-tests.php lanes/markerpdf/tests/OutputArtifactPreviewMarkdownImageBundleCurrentBaseTest.php lanes/markerpdf/tests/OutputRuntimePreviewArtifactBoundaryCurrentBaseTest.php lanes/markerpdf/tests/OutputWriterTest.php lanes/markerpdf/tests/MarkdownImageEmbedderTest.php lanes/markerpdf/tests/SingleDocumentConverterTest.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php`

- Result: `7 test files, 347 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-output-artifact-preview-markdown-image-bundle-currentbase.php`

- Passed and emitted `markdown_reference_count=3`, `image_artifact_count=3`, `embedded_reference_count=2`, `missing_reference_count=1`, `unreferenced_artifact_count=1`, `preview_data_uri_count=2`, `preview_data_uri_count_matches_embedded_references=true`, `cover_title_reference_rewritten=true`, `detail_title_reference_rewritten=true`, and all upstream-runtime execution flags false.

JSON/hygiene:

- `php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $path) { json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR); } echo "markerpdf json ok\n";'`
  - `markerpdf json ok`
- `git diff --check -- lanes/markerpdf`
  - passed with no output

Status delta:

- Behavior tests: `892 -> 894` pass / `0` fail.
- Focused assertions added in the new test file: `88`.
- Mapped current-base behavior: `629 -> 630 / 78`.

## Non-overlap

This does not repeat the accepted output artifact sanitizer, runtime preview artifact boundary, image raster/filter/color-space preview slices, PDF parser/xref/security/annotation/font/table/metadata slices, marker server pagination/error artifacts, or benchmark runtime output boundaries. The bounded new behavior is the current-base Markdown image bundle accounting plus optional-title image reference rewrite before runtime preview embedding.

## Dependency Closure

No new support component is needed. This slice reuses native PHP output writing, image filename sanitization, metadata rewriting, file hashing, and `MarkdownImageEmbedder` preview HTML generation. Full live markerPDF runtime parity remains gated on Streamlit, pypdfium2/PDFium, PIL, pdftext, Python model workers, Surya/Torch model downloads, tabled-pdf, Texify, FastAPI/Uvicorn, benchmark workflows, and external OCR/PDF helpers; this patch does not execute those dependencies.
