# markerPDF Runtime Benchmark Artifact Error JSON

Micro-slice: `runtime-benchmark-artifact-error-json-currentbase`

Session: `port-dev-markerpdf-runtime56-20260602T211350Z`

Base accepted HEAD: `0e451709894623744c6f5d4ef8d1ef3a4870fcbb`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `benchmarks/overall.py` writes per-method Markdown outputs during conversion, then opens `args.out_file` at the final report boundary and `json.dump(...)` writes the successful benchmark report. If that final output file cannot be opened/written, upstream fails fast and there is no successful report JSON artifact.

Primary upstream file inspected:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/benchmarks/overall.py`

The native PHP slice preserves that final success-report boundary while adding a WordPress-safe, review-only error artifact for benchmark gates.

## Patch

- `BenchmarkReportBuilder::writeJsonReport()` now suppresses PHP filesystem warnings and throws the existing structured `RuntimeException` when the report file is not writable.
- `BenchmarkRunner::runWithErrorTelemetry()` accepts an optional error artifact JSON path and writes it only after a benchmark runtime failure.
- The error artifact uses schema `markerpdf.benchmark_error.v1`, includes upstream source, status, message line, full telemetry, success-report-written false, review-only flags, and no Python/model/external-tool execution flags.
- `wordpress-marker-runtime-benchmark-artifact-error-json-currentbase.php` demonstrates a final `overall.json` write failure where both runner-owned Markdown outputs exist, the successful report is absent, and `overall.error.json` carries the review payload.

## Evidence

Focused baseline before edit:

`php tools/run-tests.php lanes/markerpdf/tests/BenchmarkRunnerTest.php`

Passed: `1 test files, 118 assertions, 0 failures`.

Focused test after edit:

`php tools/run-tests.php lanes/markerpdf/tests/BenchmarkRunnerTest.php`

Passed: `1 test files, 147 assertions, 0 failures`.

Adjacent runtime/benchmark family:

`php tools/run-tests.php lanes/markerpdf/tests/BenchmarkRunnerTest.php lanes/markerpdf/tests/BenchmarkReportBuilderTest.php lanes/markerpdf/tests/BenchmarkReportVerifierTest.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php lanes/markerpdf/tests/MarkerServerAdapterTest.php lanes/markerpdf/tests/SingleDocumentConverterTest.php lanes/markerpdf/tests/ChunkConversionPlannerTest.php`

Passed: `8 test files, 432 assertions, 0 failures`.

Example smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-benchmark-artifact-error-json-currentbase.php`

Passed: emitted `failed_phase=report_write`, `success_report_written=false`, `error_artifact_schema=markerpdf.benchmark_error.v1`, `error_artifact_review_only=true`, both Markdown output files present, and no Python/model/external-tool execution.

PHP lint:

- `php -l lanes/markerpdf/src/BenchmarkReportBuilder.php`
- `php -l lanes/markerpdf/src/BenchmarkRunner.php`
- `php -l lanes/markerpdf/tests/BenchmarkRunnerTest.php`
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-benchmark-artifact-error-json-currentbase.php`

All reported no syntax errors.

Final JSON/diff checks:

- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'`
- `git diff --check -- lanes/markerpdf`

Both passed.

## Status Delta

- Behavior tests move `825 -> 826` pass / `0` fail.
- Focused `BenchmarkRunnerTest.php` moves `118 -> 147` assertions.
- WordPress scenarios move `825 -> 826`.
- No mapped-denominator change claimed; this is an error-artifact refinement of already mapped upstream benchmark runtime/report behavior.

## Dependency Closure

No new support component is needed. This reuses the native benchmark runner, benchmark report/scoring paths, fail-fast telemetry, committed CI benchmark excerpts, PHP JSON encoding, filesystem write boundaries, and WordPress smoke path. Full live upstream parity remains dependency-gated on Poetry/Python setup, `pdftext`, `pypdfium2`/PDFium, Surya/Torch model downloads, CUDA memory profiling, `tabled-pdf`, Texify, Nougat execution, Streamlit/FastAPI/Uvicorn runtime paths, OCR/raster helpers, and external PDF tooling.

## Non-Overlap

This does not repeat accepted benchmark option callback mapping, successful benchmark report output persistence, score-file verification, callback sandbox mutation checks, fail-fast method/page-counter telemetry, fail-soft memory snapshot report rows, convert.py per-file error output, marker server local/remote/config errors, app config planning, runtime multiprocessing planning, or PDF parser/font/image/security/xref/page/table/form/outline/metadata current-base behavior. The bounded behavior is only review-only JSON artifact persistence for failed `benchmarks/overall.py` final report writes.
