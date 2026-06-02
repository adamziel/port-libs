# markerPDF Runtime Conversion Report Memory Error

Micro-slice: `runtime-conversion-report-memory-error-currentbase`

Session: `port-dev-markerpdf-runtime49-20260602T2036Z`

Base accepted HEAD: `d1072c4d57f8bf8b55795755ca4bcc26ff531e74`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `benchmarks/overall.py` enables CUDA memory history when `--profile_memory` is requested, writes `model_load.pickle` after model loading, writes per-document Marker snapshots named `marker_memory_{idx}.pickle`, catches snapshot dump exceptions in `stop_memory_profiling()`, logs `Failed to capture memory snapshot {error}`, disables memory-history recording, and continues the benchmark report path. The native PHP boundary keeps that fail-soft memory profiling behavior review-only while preserving successful score/report/Markdown output.

Primary upstream file inspected:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/benchmarks/overall.py`

## Patch

- `BenchmarkRunner::run()` now accepts `memory_snapshot_errors` / `memorySnapshotErrors` runtime options keyed by upstream snapshot filename when `profile_memory` is enabled.
- Successful benchmark runtime reports now include `memory_snapshot_failures`, `memory_snapshot_failure_count`, and `continues_after_memory_snapshot_failure`.
- Memory failure rows carry model-load or converter phase context, method/document/index where applicable, upstream snapshot filename, upstream-style log line, `recording_disabled_after_error=true`, `continues_after_failure=true`, and `executes_cuda_memory_history=false`.
- `wordpress-marker-runtime-conversion-report-memory-error-currentbase.php` demonstrates a WordPress benchmark gate where model-load and per-document snapshot failures are review-only and the report still writes Markdown and scores both upstream CI excerpt PDFs.

## Evidence

Focused baseline before edit:

`php tools/run-tests.php lanes/markerpdf/tests/BenchmarkRunnerTest.php`

Passed: `1 test files, 88 assertions, 0 failures`.

Focused test after edit:

`php tools/run-tests.php lanes/markerpdf/tests/BenchmarkRunnerTest.php`

Passed: `1 test files, 118 assertions, 0 failures`.

Adjacent runtime/benchmark family:

`php tools/run-tests.php lanes/markerpdf/tests/BenchmarkRunnerTest.php lanes/markerpdf/tests/BenchmarkReportBuilderTest.php lanes/markerpdf/tests/BenchmarkReportVerifierTest.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php`

Passed: `5 test files, 266 assertions, 0 failures`.

Example smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-conversion-report-memory-error-currentbase.php`

Passed: emitted `memory_snapshot_failure_count=2`, `model_load.pickle`, `marker_memory_1.pickle`, `continues_after_memory_snapshot_failure=true`, two written Markdown files, positive report score, and no CUDA/Python/model/external-tool execution.

PHP lint:

- `php -l lanes/markerpdf/src/BenchmarkRunner.php`
- `php -l lanes/markerpdf/tests/BenchmarkRunnerTest.php`
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-conversion-report-memory-error-currentbase.php`

All reported no syntax errors.

Final JSON/diff checks:

- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'`
- `git diff --check -- lanes/markerpdf`

Both passed.

## Status Delta

- Behavior tests move `787 -> 788` pass / `0` fail.
- Focused `BenchmarkRunnerTest.php` moves `88 -> 118` assertions.
- WordPress scenarios move `787 -> 788`.
- No mapped-denominator change claimed; this is a report-level refinement of already mapped upstream benchmark memory profiling behavior.

## Dependency Closure

No new support component is needed. This reuses the native benchmark runner, benchmark report/scoring paths, existing memory snapshot failure helper, committed CI benchmark excerpts, WordPress smoke path, and PHP test harness. Full live upstream parity remains dependency-gated on Poetry/Python setup, `pdftext`, `pypdfium2`/PDFium, Surya/Torch model downloads, CUDA memory profiling, `tabled-pdf`, Texify, Nougat execution, Streamlit/FastAPI/Uvicorn runtime paths, OCR/raster helpers, and external PDF tooling.

## Non-Overlap

This does not repeat accepted runtime benchmark API option mapping, standalone `memorySnapshotFailureReport()` metadata, convert.py per-file error output, fail-fast benchmark error telemetry, callback sandbox checks, report output persistence, marker server local/remote errors, app/server config planning, runtime multiprocessing planning, or PDF parser/font/image/security/xref/page/table/form/outline/metadata current-base behavior. The bounded behavior is only successful benchmark conversion reports carrying upstream-style fail-soft memory snapshot dump errors.
