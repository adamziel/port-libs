# markerPDF Runtime Server Benchmark Output Error Roundtrip

Micro-slice: `runtime-server-benchmark-output-error-roundtrip-currentbase`

Session: `port-dev-markerpdf-runtime64-20260602T213951Z`

Base accepted HEAD: `c3a3b3436899d5af64fa2dad7e137908759c83df`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` has three relevant runtime boundaries:

- `marker_server.py::convert_pdf_from_upload()` catches valid-PDF upload conversion failures and returns `{"success": False, "error": str(e)}`, then removes the temporary upload in `finally`.
- `benchmarks/overall.py::main()` writes optional per-method Markdown outputs, writes `args.out_file` with `json.dump(...)` only on successful report completion, and otherwise fails fast.
- `marker/output.py::save_markdown()` keeps output artifacts as files on disk with JSON metadata, which is the native boundary this lane mirrors for WordPress review artifacts.

Primary upstream files inspected:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker_server.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/benchmarks/overall.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/output.py`

## Patch

- Added `BenchmarkRunner::writeServerBenchmarkErrorArtifactJson()` for failed marker server upload responses that must be preserved as benchmark/output review artifacts.
- Added `BenchmarkRunner::readServerBenchmarkErrorArtifactJson()` to validate schema, fail-fast status, review-only status, artifact hash/size, and exact server error preservation on readback.
- Added `MarkerRuntimeServerBenchmarkOutputErrorRoundtripCurrentBaseTest.php` with a marker server upload remote-error response, upload cleanup, JSON artifact write, JSON artifact readback, no success report, no failed Markdown, and no live runtime execution flags.
- Added `wordpress-marker-runtime-server-benchmark-output-error-roundtrip-currentbase.php` as a local WordPress benchmark/import smoke.
- Updated lane status and manifest with one mapped current-base runtime behavior.

## Evidence

Red-first focused run after adding the test:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeServerBenchmarkOutputErrorRoundtripCurrentBaseTest.php`

Failed as expected:

- `Call to undefined method PortLibs\MarkerPDF\BenchmarkRunner::writeServerBenchmarkErrorArtifactJson()`
- expected `InvalidArgumentException`, got missing-method `Error`

Final focused test:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeServerBenchmarkOutputErrorRoundtripCurrentBaseTest.php`

Passed: `1 test files, 41 assertions, 0 failures`.

Adjacent runtime/server/benchmark/output family:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeServerBenchmarkOutputErrorRoundtripCurrentBaseTest.php lanes/markerpdf/tests/BenchmarkRunnerTest.php lanes/markerpdf/tests/MarkerServerAdapterTest.php lanes/markerpdf/tests/BenchmarkReportBuilderTest.php lanes/markerpdf/tests/BenchmarkReportVerifierTest.php lanes/markerpdf/tests/OutputWriterTest.php`

Passed: `6 test files, 315 assertions, 0 failures`.

Example smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-server-benchmark-output-error-roundtrip-currentbase.php`

Passed: emitted `server_success=false`, `server_error="Remote marker API response is missing request_check_url."`, `artifact_schema="markerpdf.server_benchmark_error.v1"`, `artifact_sha256_matches_readback=true`, `roundtrip_preserves_server_error=true`, `upload_removed=true`, `request_count=1`, `success_report_written=false`, `writes_markdown_after_failure=false`, and all FastAPI/Uvicorn/live HTTP/Python/model/external-tool flags false.

PHP lint:

- `php -l lanes/markerpdf/src/BenchmarkRunner.php`
- `php -l lanes/markerpdf/tests/MarkerRuntimeServerBenchmarkOutputErrorRoundtripCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-server-benchmark-output-error-roundtrip-currentbase.php`

All reported no syntax errors.

Metadata/diff checks:

- `php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR); echo $file, " OK\n"; }'`
- `git diff --check -- lanes/markerpdf`

Both passed.

## Status Delta

- Behavior tests move `859 -> 860` pass / `0` fail.
- Focused test adds `41` assertions.
- WordPress scenarios move `859 -> 860`.
- Mapped markerPDF semantics move `605 -> 606 / 78`.

## Dependency Closure

No new support component is needed. This reuses the native marker server adapter, benchmark runner, JSON artifact path, PHP filesystem read/write/hash boundaries, and WordPress smoke path. Full upstream parity remains dependency-gated on live FastAPI/Uvicorn, Python `requests`, `pdftext`, `pypdfium2`/PDFium, Surya/Torch model downloads, CUDA profiling, tabled-pdf, Texify, Nougat, Streamlit, OCR/raster helpers, and external PDF tooling.

## Non-Overlap

This does not repeat accepted marker server config errors, upload conversion error payloads, remote polling errors/exhaustion, benchmark runtime API callbacks, benchmark fail-fast telemetry, final benchmark report-write error artifacts, successful benchmark output tables, output Markdown/image sanitization, convert.py batch errors, Streamlit planning, PDF parser/security/font/xref/table/image/form/page/outline/metadata current-base slices, or live runtime execution. The bounded behavior is only the roundtrip boundary that persists a failed marker server upload response as benchmark/output review JSON and validates the same server error on readback.
