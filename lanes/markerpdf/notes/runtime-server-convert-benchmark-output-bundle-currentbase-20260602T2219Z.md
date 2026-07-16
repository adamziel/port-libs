# markerPDF runtime server convert benchmark output bundle currentbase

Micro-slice: `runtime-server-convert-benchmark-output-bundle-currentbase`
Session: `port-dev-markerpdf-runtime70-20260602T215950Z`
Base accepted HEAD: `5cd9230be04519cb4852fe5076346eb28b7e6962`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `marker_server.py::convert_pdf_local()` returns a success payload with `markdown`, base64-encoded `images`, `metadata`, and `success=true`.
- Upstream `marker/output.py::save_markdown()` writes Markdown, a sibling `_meta.json`, and PNG image files under a document subfolder.
- Upstream `benchmarks/overall.py::main()` writes per-method Markdown outputs when `--md_out_path` is supplied, then writes the final `overall.json` report separately. This slice maps the successful server-convert output bundle; it does not run FastAPI, Uvicorn, Python models, Nougat, PDFium, or external PDF tools.

## Implementation

- Added `BenchmarkRunner::writeServerBenchmarkOutputBundle()` for successful marker server conversion responses.
- The method decodes marker-server base64 image values into the existing `OutputWriter::saveMarkdownArtifactBoundary()` path, so Markdown, metadata, and images are persisted with the same artifact rules as accepted output slices.
- Added a sanitized `markerpdf.server_benchmark_output_bundle.v1` JSON manifest and `readServerBenchmarkOutputBundleJson()` roundtrip validator.
- The persisted bundle manifest records document, context, artifact paths, hashes, and counts while omitting raw base64 image strings, raw image bytes, original unsafe image filenames, and runtime preview HTML.
- Added `MarkerRuntimeServerConvertBenchmarkOutputBundleCurrentBaseTest.php` and `wordpress-marker-runtime-server-convert-benchmark-output-bundle-currentbase.php`.

## Red-First Boundary

Before implementation, the focused test failed on the current accepted source:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeServerConvertBenchmarkOutputBundleCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL bundles successful marker server conversion output as benchmark artifacts
Call to undefined method PortLibs\MarkerPDF\BenchmarkRunner::writeServerBenchmarkOutputBundle()
FAIL rejects failed server responses and malformed output bundle artifacts
Expected InvalidArgumentException, got Error
1 test files, 4 assertions, 2 failures
```

## Verification

```text
php -l lanes/markerpdf/src/BenchmarkRunner.php
php -l lanes/markerpdf/tests/MarkerRuntimeServerConvertBenchmarkOutputBundleCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-marker-runtime-server-convert-benchmark-output-bundle-currentbase.php
```

Passed.

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeServerConvertBenchmarkOutputBundleCurrentBaseTest.php
```

Passed: 1 test file, 57 assertions, 0 failures.

```text
php tools/run-tests.php lanes/markerpdf/tests/BenchmarkRunnerTest.php lanes/markerpdf/tests/MarkerServerAdapterTest.php lanes/markerpdf/tests/OutputRuntimePreviewArtifactBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeServerBenchmarkOutputErrorRoundtripCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeServerConvertBenchmarkOutputBundleCurrentBaseTest.php
```

Passed: 5 test files, 374 assertions, 0 failures.

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-server-convert-benchmark-output-bundle-currentbase.php
```

Passed: emitted `bundle_schema=markerpdf.server_benchmark_output_bundle.v1`, `roundtrip_preserves_output_bundle=true`, `image_filename=preview_image.png`, `image_payload_excluded_from_markdown=true`, `upload_removed=true`, and all FastAPI/Uvicorn/live HTTP/Python/model/external-tool flags false.

## Status Delta

- Behavior tests move `883 -> 885 pass / 0 fail`.
- Mapped markerPDF/runtime semantics move `623 -> 624 / 78`.

## Non-Overlap

This does not repeat accepted marker server config/upload/polling error boundaries, failed server benchmark error JSON roundtrip, failed benchmark report-write error artifacts, successful benchmark score table/report output, output runtime preview artifacts, callback sandboxing, convert.py multiprocessing planning, or PDF parser/font/image/security/xref/page/table/form/outline/metadata current-base slices. The new behavior is only the successful marker server conversion response bundle that persists Markdown, metadata, images, and a sanitized benchmark-output manifest.

## Dependency Closure

No new support component is needed. This reuses the native marker server adapter, benchmark runner, output writer, filesystem artifact hashing, JSON manifest path, and WordPress smoke. Full upstream markerPDF parity remains dependency-gated on live FastAPI/Uvicorn, Python `requests`, `pdftext`, `pypdfium2`/PDFium, Surya/Torch model downloads, CUDA profiling, tabled-pdf, Texify, Nougat, Streamlit, OCR/raster helpers, and external PDF tooling.
