# output-markdown-image-artifact-quality-currentbase

Session: `port-dev-markerpdf-output75-20260602T222511Z`
Base accepted HEAD: `dea63aa7e627de2d478a25a4f111e872b79036af`

## Source Truth

Upstream markerPDF is pinned in the lane manifest at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.

- `marker/output.py::save_markdown()` writes Markdown, `_meta.json`, and saves each image artifact as PNG.
- `marker/images/save.py::images_to_dict()` keys image artifacts by deterministic `"{page}_image_{index}.png"` filenames.
- `marker_app.py::img_to_html()` and `markdown_insert_images()` serialize image objects as PNG data URI preview HTML from Markdown image references.

Primary upstream files inspected:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/output.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/save.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker_app.py

## Implementation

`OutputWriter::saveMarkdownArtifactBoundary()` now audits each persisted image artifact with pure PHP PNG review metadata before WordPress media import. Each image row records signature validity, IHDR width/height, bit depth, color type, interlace method, chunk types, CRC state, IEND presence, importability, and quality warnings.

The `markdown_image_bundle` now includes an `image_quality` aggregate summarizing importable versus malformed artifacts, referenced malformed artifacts, malformed artifacts still embedded in runtime-only preview HTML, and warning counts. This keeps the upstream persisted Markdown and Streamlit-preview boundary intact while giving WordPress import screens a deterministic media-quality gate without running PIL, Streamlit, pypdfium, Python models, or external PDF tools.

`examples/wordpress-output-artifact-preview-markdown-image-bundle-currentbase.php` now emits the quality summary with two valid referenced PNG artifacts and one malformed review-only artifact.

## Verification

PHP lint:

- `php -l lanes/markerpdf/src/OutputWriter.php`
  - `No syntax errors detected in lanes/markerpdf/src/OutputWriter.php`
- `php -l lanes/markerpdf/tests/OutputArtifactPreviewMarkdownImageBundleCurrentBaseTest.php`
  - `No syntax errors detected in lanes/markerpdf/tests/OutputArtifactPreviewMarkdownImageBundleCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-output-artifact-preview-markdown-image-bundle-currentbase.php`
  - `No syntax errors detected in lanes/markerpdf/examples/wordpress-output-artifact-preview-markdown-image-bundle-currentbase.php`

Focused output test:

`php tools/run-tests.php lanes/markerpdf/tests/OutputArtifactPreviewMarkdownImageBundleCurrentBaseTest.php`

- PASS bundles saved markdown image artifacts with optional-title runtime preview targets
- PASS audits saved markdown image artifact png quality for wordpress media import
- PASS reports bundle accounting even when runtime preview html is not requested
- Result: `1 test files, 119 assertions, 0 failures`

Adjacent output/runtime gate:

`php tools/run-tests.php lanes/markerpdf/tests/OutputArtifactPreviewMarkdownImageBundleCurrentBaseTest.php lanes/markerpdf/tests/OutputRuntimePreviewArtifactBoundaryCurrentBaseTest.php lanes/markerpdf/tests/OutputWriterTest.php lanes/markerpdf/tests/MarkdownImageEmbedderTest.php lanes/markerpdf/tests/SingleDocumentConverterTest.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php`

- Result: `7 test files, 378 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-output-artifact-preview-markdown-image-bundle-currentbase.php`

- Passed and emitted `markdown_reference_count=3`, `image_artifact_count=3`, `embedded_reference_count=2`, `missing_reference_count=1`, `unreferenced_artifact_count=1`, `preview_data_uri_count=2`, `wordpress_media_importable_count=2`, `wordpress_media_unimportable_count=1`, `unimportable_image_artifacts=["5_image_0.png"]`, `quality_warning_counts={"invalid_png_signature":1}`, and all upstream-runtime execution flags false.

JSON/hygiene:

- `php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $path) { json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR); } echo "markerpdf json ok\n";'`
  - `markerpdf json ok`
- `git diff --check -- lanes/markerpdf`
  - passed with no output

Status delta:

- Behavior tests: `910 -> 911` pass / `0` fail.
- Focused test delta in the edited output bundle test file: `+31` assertions and `+1` PASS case.
- Mapped current-base behavior: `640 -> 641 / 78`.

## Non-overlap

This does not repeat the accepted output artifact sanitizer, the runtime preview artifact boundary, Markdown image target accounting, optional-title rewrite, image raster/filter/color-space preview slices, PDF parser/xref/security/annotation/font/table/metadata slices, marker server pagination/error artifacts, or benchmark runtime output boundaries. The bounded new behavior is the saved Markdown image artifact quality audit for PNG importability before WordPress media ingestion.

## Dependency Closure

No new support component is needed. This slice reuses native PHP output writing, file hashing, image artifact persistence, and runtime preview embedding, with a pure-PHP PNG chunk inspector. Full live markerPDF runner parity remains gated on Streamlit, pypdfium2/PDFium, PIL, pdftext, Python model workers, Surya/Torch model downloads, tabled-pdf, Texify, FastAPI/Uvicorn, benchmark workflows, OCRMyPDF/Tesseract, Ghostscript, and external OCR/PDF helpers; this patch does not execute those dependencies.
