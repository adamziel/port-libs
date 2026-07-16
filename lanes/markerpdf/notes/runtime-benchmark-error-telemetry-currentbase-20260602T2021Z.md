# markerPDF Runtime Benchmark Error Telemetry

Micro-slice: `runtime-benchmark-error-telemetry-currentbase`

Session: `port-dev-markerpdf-runtime47-20260602T2021Z`

Base accepted HEAD: `aba54dbcf7a8eaa01ed36c5fcab3cba80da2f4fa`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `benchmarks/overall.py` fails fast when page counting or a conversion method raises: it reads each same-basename reference Markdown file, opens the PDF with PDFium for page count, invokes Marker/Nougat methods, then writes only runner-owned Markdown output after conversion returns. The native PHP boundary keeps that fail-fast behavior while exposing method/document/phase telemetry for WordPress benchmark gates.

Primary upstream file inspected:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/benchmarks/overall.py`

## Patch

- `BenchmarkRunner::runWithErrorTelemetry()` wraps the existing fail-fast `run()` method and returns review-only telemetry instead of throwing through a WordPress import gate.
- `BenchmarkRunner` tracks active benchmark phases for reference reads, page counting, converter callbacks, Markdown writes, and report writes.
- Failure telemetry includes phase, method, document, benchmark index, PDF/reference/output paths, memory snapshot name, callback sandbox flag, message line, traceback, fail-fast markers, no failed Markdown write flag, and non-execution flags.
- The default `BenchmarkRunner::run()` behavior still throws on upstream-style runtime failures.
- `wordpress-marker-runtime-benchmark-error-telemetry-currentbase.php` demonstrates a benchmark where `multicolcnn.pdf` writes runner-owned Markdown, `switch_trans.pdf` fails during Marker conversion, and the failed document does not write Markdown.

## Evidence

Focused test:

`php tools/run-tests.php lanes/markerpdf/tests/BenchmarkRunnerTest.php`

Passed: `1 test files, 88 assertions, 0 failures`.

Adjacent runtime/benchmark family:

`php tools/run-tests.php lanes/markerpdf/tests/BenchmarkRunnerTest.php lanes/markerpdf/tests/BenchmarkReportBuilderTest.php lanes/markerpdf/tests/BenchmarkReportVerifierTest.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php lanes/markerpdf/tests/MarkerServerAdapterTest.php lanes/markerpdf/tests/SingleDocumentConverterTest.php lanes/markerpdf/tests/ChunkConversionPlannerTest.php`

Passed: `8 test files, 345 assertions, 0 failures`.

Example smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-benchmark-error-telemetry-currentbase.php`

Passed: emitted `failed_phase=converter`, `failed_method=marker`, `failed_document=switch_trans.pdf`, `failed_memory_snapshot=marker_memory_1.pickle`, `continues_after_failure=false`, `writes_failed_markdown=false`, `preserved_prior_markdown=true`, and no Python/model/external-tool execution.

PHP lint:

- `php -l lanes/markerpdf/src/BenchmarkRunner.php`
- `php -l lanes/markerpdf/tests/BenchmarkRunnerTest.php`
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-benchmark-error-telemetry-currentbase.php`

All reported no syntax errors.

Final checks:

- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'`
- `git diff --check -- lanes/markerpdf`

Both passed.

## Status Delta

- Behavior tests move `768 -> 769` pass / `0` fail.
- Mapped markerPDF semantics move `546 -> 547 / 78`.
- WordPress scenarios move `768 -> 769`.

## Dependency Closure

No new support component is needed. This reuses the native benchmark runner, callback sandbox, benchmark report/scoring paths, committed CI benchmark excerpts, PHP exception traces, and lane test harness. Full upstream runtime parity remains dependency-gated on Poetry/Python setup, `pdftext`, `pypdfium2`/PDFium, Surya/Torch model downloads, `tabled-pdf`, Texify, Nougat execution, CUDA profiling, Streamlit/FastAPI/Uvicorn runtime paths, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted runtime benchmark API callback option mapping, benchmark output persistence, score-file verification, callback sandbox mutation checks, convert.py per-file error metadata, marker server local/remote/upload error handling, server config errors, app config planning, runtime conversion/multiprocessing planning, or PDF parser/font/image/security/xref/page/table/form/outline/metadata current-base behavior. The bounded behavior is only structured fail-fast error telemetry for `benchmarks/overall.py` page-counter and conversion-method failures.
