# markerPDF Runtime Server Upload Benchmark Error

Micro-slice: `runtime-server-upload-benchmark-error-currentbase`

Session: `port-dev-markerpdf-runtime75-20260602T222511Z`

Base accepted HEAD: `dea63aa7e627de2d478a25a4f111e872b79036af`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` has the relevant runtime behavior in:

- `marker_server.py`: upload conversion saves the uploaded PDF, routes local or remote conversion, returns a failed JSON body for conversion exceptions, and deletes the temp upload in `finally`.
- `benchmarks/overall.py`: benchmark conversion failures fail fast before final report JSON/Markdown success output is completed.
- `marker/output.py`: successful conversion artifacts are persisted as Markdown, metadata JSON, and PNG image files.

Primary source URLs inspected:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker_server.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/benchmarks/overall.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/output.py`

## Patch

- Added `BenchmarkRunner::writeServerUploadBenchmarkResult()`, a native composition boundary that routes successful marker server upload responses into existing benchmark output bundles and failed upload responses into existing review-only server error artifacts.
- The error path creates the artifact parent directory when needed, validates roundtrip preservation, records upload cleanup/request-count context, and never writes failed Markdown or a success report.
- Added `MarkerRuntimeServerUploadBenchmarkErrorCurrentBaseTest.php` with failed remote upload, successful local upload, and invalid response guard coverage.
- Added `wordpress-marker-runtime-server-upload-benchmark-error-currentbase.php` as a WordPress benchmark queue smoke.
- Updated lane manifest/status with one mapped current-base runtime behavior.

## Evidence

- `php -l lanes/markerpdf/src/BenchmarkRunner.php`: no syntax errors.
- `php -l lanes/markerpdf/tests/MarkerRuntimeServerUploadBenchmarkErrorCurrentBaseTest.php`: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-server-upload-benchmark-error-currentbase.php`: no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeServerUploadBenchmarkErrorCurrentBaseTest.php`: passed, `1 test files, 63 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeServerUploadBenchmarkErrorCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeServerUploadPaginationErrorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeServerBenchmarkOutputErrorRoundtripCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeServerConvertBenchmarkOutputBundleCurrentBaseTest.php lanes/markerpdf/tests/MarkerServerAdapterTest.php lanes/markerpdf/tests/BenchmarkRunnerTest.php lanes/markerpdf/tests/OutputWriterTest.php`: passed, `7 test files, 430 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-marker-runtime-server-upload-benchmark-error-currentbase.php`: passed; emitted `result_kind="error_artifact"`, `roundtrip_preserves_server_error=true`, `upload_removed=true`, `benchmark_output_bundle_written=false`, `success_report_written=false`, `writes_markdown_after_failure=false`, and all live runtime execution flags false.

## Status Delta

- Behavior tests move `910 -> 911` pass / `0` fail.
- Focused test adds `63` assertions.
- WordPress scenarios move `910 -> 911`.
- Mapped markerPDF semantics move `640 -> 641 / 78`.

## Dependency Closure

No new support component is needed. This slice reuses `MarkerServerAdapter`, `BenchmarkRunner`, `OutputWriter`, JSON artifact read/write/hash helpers, PHP filesystem boundaries, and the existing WordPress smoke pattern. Full upstream runtime parity remains gated by FastAPI/Uvicorn, Python `requests`, pdftext, pypdfium2/PDFium, Surya/Torch model downloads, tabled-pdf, Texify, Nougat, Streamlit/PIL, OCR/raster helpers, CUDA profiling, and external PDF tooling.

## Non-Overlap

This does not repeat marker server config errors, upload pagination cleanup, remote polling errors, standalone server error artifact roundtrips, successful server output bundles, benchmark runtime telemetry, benchmark report-write errors, output Markdown/image sanitization, or PDF parser/security/font/xref/table/image/form/page/outline/metadata slices. The bounded behavior is only the upload benchmark result selector that composes existing success/error artifact boundaries for WordPress review queues.
