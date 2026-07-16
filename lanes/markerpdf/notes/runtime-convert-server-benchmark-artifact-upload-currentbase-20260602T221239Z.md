# markerPDF Runtime Convert Server Benchmark Artifact Upload

Micro-slice: `runtime-convert-server-benchmark-artifact-upload-currentbase`

Session: `port-dev-markerpdf-runtime72-20260602T221239Z`

Base accepted HEAD: `36d3abb94323edf47dc54936168141773ec380c2`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` has the relevant runtime boundaries:

- `marker_server.py::convert_pdf_from_upload()` accepts only `application/pdf`, saves the uploaded file under `UPLOAD_DIRECTORY`, routes to local or remote conversion with `params.filepath` set to the saved path, catches valid-PDF conversion errors as `{"success": False, "error": str(e)}`, and removes the temporary upload in `finally`.
- `marker_server.py::convert_pdf_remote()` posts multipart file/options to the Datalab endpoint, polls `request_check_url`, and returns the completed poll payload.
- `benchmarks/overall.py::main()` records per-file `time`, `score`, and `pages`, writes optional per-method Markdown outputs, then writes the final JSON report only after successful completion.
- `marker/output.py::save_markdown()` stores output artifacts as files on disk with JSON metadata and PNG image files rather than embedding upload bytes in benchmark reports.

Primary upstream files inspected:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker_server.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/benchmarks/overall.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/output.py`

## Red First

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeConvertServerBenchmarkArtifactUploadCurrentBaseTest.php`

Failed as expected before implementation:

- `Call to undefined method PortLibs\MarkerPDF\BenchmarkRunner::writeServerBenchmarkUploadArtifactJson()`
- expected `InvalidArgumentException`, got missing-method `Error`
- Result: `1 test files, 6 assertions, 2 failures`

## Patch

- Added `BenchmarkRunner::writeServerBenchmarkUploadArtifactJson()` for successful marker server upload conversion payloads.
- Added `BenchmarkRunner::readServerBenchmarkUploadArtifactJson()` to validate schema, success status, review-only status, image summarization, and exact Markdown hash preservation.
- Server response artifacts preserve Markdown, Markdown SHA-256, metadata, sorted metadata keys, benchmark context, score/page/time context, upload cleanup status, and image hash/size summaries.
- Server response artifacts exclude uploaded PDF bytes and raw base64 image payloads from the JSON review artifact.
- Added `MarkerRuntimeConvertServerBenchmarkArtifactUploadCurrentBaseTest.php`.
- Added `wordpress-marker-runtime-convert-server-benchmark-artifact-upload-currentbase.php`.
- Updated lane status and upstream manifest with one mapped current-base runtime behavior.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeConvertServerBenchmarkArtifactUploadCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS roundtrips successful marker server uploads through benchmark artifact JSON
PASS rejects failed or malformed server upload benchmark artifacts

1 test files, 62 assertions, 0 failures
```

Adjacent runtime/server/benchmark/output family:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeConvertServerBenchmarkArtifactUploadCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeServerBenchmarkOutputErrorRoundtripCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeConvertServerOutputPaginationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeServerUploadPaginationErrorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerServerAdapterTest.php lanes/markerpdf/tests/BenchmarkRunnerTest.php lanes/markerpdf/tests/BenchmarkReportBuilderTest.php lanes/markerpdf/tests/BenchmarkReportVerifierTest.php lanes/markerpdf/tests/OutputRuntimePreviewArtifactBoundaryCurrentBaseTest.php lanes/markerpdf/tests/OutputWriterTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 529 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-convert-server-benchmark-artifact-upload-currentbase.php
```

Passed: emitted `request_methods=["POST","GET"]`, `upload_removed=true`, `artifact_schema="markerpdf.server_benchmark_upload.v1"`, `artifact_sha256_matches_readback=true`, `roundtrip_preserves_server_success=true`, `roundtrip_preserves_markdown_hash=true`, `image_payloads_summarized=true`, `raw_upload_bytes_excluded=true`, `raw_image_base64_excluded=true`, `success_report_written=true`, and all FastAPI/Uvicorn/live HTTP/Python/model/external-tool flags false.

PHP lint:

```text
php -l lanes/markerpdf/src/BenchmarkRunner.php
php -l lanes/markerpdf/tests/MarkerRuntimeConvertServerBenchmarkArtifactUploadCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-marker-runtime-convert-server-benchmark-artifact-upload-currentbase.php
```

All reported no syntax errors.

Metadata and whitespace checks:

```text
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR); echo $file, " OK\n"; }'
git diff --check -- lanes/markerpdf
```

Both passed.

## Status Delta

- Behavior tests move `895 -> 896` pass / `0` fail.
- Focused test adds `62` assertions.
- Mapped markerPDF semantics move `631 -> 632 / 78`.
- WordPress scenarios move `895 -> 896`.

## Dependency Closure

No new support component is needed. This reuses the native `MarkerServerAdapter`, `BenchmarkRunner`, PHP JSON/file hashing, existing server upload remote callback boundary, and WordPress smoke pattern. Full upstream runtime parity remains dependency-gated by live FastAPI/Uvicorn execution, Python `requests`, `pdftext`, `pypdfium2`/PDFium, Surya/Torch model downloads, CUDA profiling, tabled-pdf, Texify, Nougat, Streamlit, OCR/raster helpers, and external PDF tooling.

## Non-Overlap

This does not repeat accepted marker server startup config errors, upload pagination/error cleanup, failed server benchmark error artifacts, remote polling malformed-status/exhaustion handling, runtime benchmark API callbacks, callback sandbox mutation checks, final benchmark report-write error artifacts, successful benchmark output tables, server output pagination markers, output Markdown/image artifact sanitization, convert.py batch errors, Streamlit planning, PDF parser/security/font/xref/table/image/form/page/outline/metadata current-base slices, or live runtime execution. The bounded behavior is only successful marker server upload conversion payloads archived as sanitized benchmark review JSON.
