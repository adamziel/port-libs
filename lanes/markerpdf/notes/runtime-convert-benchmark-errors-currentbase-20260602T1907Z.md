# markerPDF Runtime Convert Benchmark Errors

Micro-slice: `runtime-convert-benchmark-errors-currentbase`

Base accepted HEAD: `78dacbd21ee6b9a83b42fbcf69facc371244266b`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` has two bounded runtime error surfaces relevant to WordPress import queues:

- `convert.py::process_single_pdf` catches per-file conversion exceptions, prints an `Error converting {filepath}: {error}` line plus formatted traceback, and does not write Markdown for that failed file.
- `benchmarks/overall.py::stop_memory_profiling` catches CUDA memory snapshot dump failures, logs the failed snapshot message, then still disables CUDA memory-history recording.

Primary upstream files inspected:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/benchmarks/overall.py`

## Patch

- `BatchConverter::processFile()` now returns upstream-style per-file error output metadata for caught conversion failures: message line, PHP traceback text, review-only marker, no Markdown write flag, and explicit non-execution flags.
- `BenchmarkRunner::memorySnapshotFailureReport()` records upstream `stop_memory_profiling` failure metadata without touching CUDA or Python runtime state.
- `wordpress-marker-runtime-convert-benchmark-errors-currentbase.php` demonstrates a WordPress PDF import batch where one file converts and one model-boundary error stays review-only, plus benchmark memory snapshot failure metadata.

## Evidence

Red-first focused run before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/BenchmarkRunnerTest.php`

Failed as expected: `2 test files, 73 assertions, 2 failures` for missing `writes_markdown`/`error_output` fields and missing `BenchmarkRunner::memorySnapshotFailureReport()`.

Final focused run:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/BenchmarkRunnerTest.php`

Passed: `2 test files, 84 assertions, 0 failures`.

Adjacent runtime/benchmark family:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/BenchmarkRunnerTest.php lanes/markerpdf/tests/BenchmarkReportBuilderTest.php lanes/markerpdf/tests/BenchmarkReportVerifierTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php lanes/markerpdf/tests/ChunkConversionPlannerTest.php lanes/markerpdf/tests/SingleDocumentConverterTest.php lanes/markerpdf/tests/MarkerServerAdapterTest.php`

Passed: `8 test files, 241 assertions, 0 failures`.

Example smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-convert-benchmark-errors-currentbase.php`

Passed: emitted `converted=1`, `errors=1`, failed document `model-error.pdf`, review-only error output, no failed Markdown write, `marker_memory_0.pickle` benchmark memory failure metadata, and all execution flags false.

PHP lint:

- `php -l lanes/markerpdf/src/BatchConverter.php`
- `php -l lanes/markerpdf/src/BenchmarkRunner.php`
- `php -l lanes/markerpdf/tests/BatchConverterTest.php`
- `php -l lanes/markerpdf/tests/BenchmarkRunnerTest.php`
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-convert-benchmark-errors-currentbase.php`

All reported no syntax errors.

JSON/diff checks:

- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
- `git diff --check -- lanes/markerpdf`

Both passed.

## Dependency Closure

No new support component is needed. This reuses the existing native batch converter, benchmark runner, output writer, JSON smoke path, and test harness. Full upstream runtime parity remains dependency-gated on Python, Torch/CUDA memory profiling, Surya/Texify/tabled model loading, pdftext, pypdfium2/PDFium, Nougat, Streamlit/FastAPI/Uvicorn, OCR/rendering helpers, and live benchmark workflow execution.

## Non-Overlap

This does not repeat runtime38 `convert.py` multiprocessing/model-handoff planning, runtime39 score-verifier file dispatch, benchmark report output persistence, benchmark scoring thresholds, marker server local/remote API error handling, single-document artifact writing, chunk device sharding, or PDF parser/font/image/security/xref/page/table/form/outline/metadata behavior. The bounded behavior is only conversion-error review metadata plus benchmark memory snapshot failure metadata.
