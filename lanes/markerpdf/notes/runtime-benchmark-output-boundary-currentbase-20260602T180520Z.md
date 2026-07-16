# markerPDF runtime benchmark output boundary

Session: `port-dev-markerpdf-runtime36pdf-20260602T180520Z`

Micro-slice: `runtime-benchmark-output-boundary-currentbase-20260602T180520Z`

Base accepted HEAD: `25465d4bad4c4ed7e39379fb65c3e5365a4df98d`

## Source Truth

Upstream `sddai/markerPDF` at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` writes benchmark output in `benchmarks/overall.py`: it builds per-method file stats, writes `args.out_file` with pretty JSON, writes optional Markdown outputs as `{method}_{md_filename}`, then builds `summary_table` and `score_table` rows before printing them through `tabulate`.

Source URLs used:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/benchmarks/overall.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/output.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/run_marker_app.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker_app.py`

The native PHP boundary for this slice is the benchmark runtime/output artifact surface only. It records benchmark report JSON and table rows without executing Python, model workers, Nougat, Streamlit, pypdfium/PDFium, PIL rendering, or external PDF/OCR tools.

## Red First

The accepted base already built the in-memory report and optional Markdown outputs, but it did not expose the upstream final output boundary:

- `BenchmarkReportBuilder::outputTables()` did not exist.
- `BenchmarkReportBuilder::writeJsonReport()` did not exist.
- `BenchmarkRunner::run()` could not write the upstream `out_file` equivalent or return summary/score table rows.

The new focused assertions cover those missing boundaries.

## Implementation

- `BenchmarkReportBuilder` now validates benchmark report output shape, returns upstream-style summary and score table source rows, and writes pretty JSON reports.
- `BenchmarkRunner::run()` accepts an optional report output path, writes the JSON report when provided, returns `report_output`, and always returns `output_tables` for downstream review.
- `wordpress-benchmark-output-boundary.php` runs the supplied upstream CI benchmark excerpts through marker/nougat callbacks, emits the report output basename, summary headers, score headers, Markdown output basenames, and explicit no-runtime-execution flags.

## Verification

```sh
php tools/run-tests.php lanes/markerpdf/tests/BenchmarkReportBuilderTest.php lanes/markerpdf/tests/BenchmarkRunnerTest.php
```

Passed: `2 test files, 46 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/BenchmarkReportBuilderTest.php lanes/markerpdf/tests/BenchmarkRunnerTest.php lanes/markerpdf/tests/BenchmarkReportVerifierTest.php lanes/markerpdf/tests/OutputWriterTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php
```

Passed: `5 test files, 94 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-benchmark-output-boundary.php
```

Passed: emitted `report_output_basename="overall.json"`, `report_methods=["marker","nougat"]`, summary headers, score headers for `multicolcnn.pdf` and `switch_trans.pdf`, 4 written Markdown files, `passes_upstream_ci_marker_thresholds=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```sh
php -l lanes/markerpdf/src/BenchmarkReportBuilder.php
php -l lanes/markerpdf/src/BenchmarkRunner.php
php -l lanes/markerpdf/tests/BenchmarkReportBuilderTest.php
php -l lanes/markerpdf/tests/BenchmarkRunnerTest.php
php -l lanes/markerpdf/examples/wordpress-benchmark-output-boundary.php
```

Passed: no syntax errors detected.

```sh
git diff --check -- lanes/markerpdf
```

Passed: no whitespace errors.

## Status Delta

- Behavior tests move `621 -> 623`.
- Mapped markerPDF semantics move `453 -> 454 / 78`.
- WordPress scenarios move `621 -> 623`.

## Dependency Closure

No new support component is needed. This slice reuses the native benchmark scorer, report builder, benchmark runner, supplied CI benchmark excerpts, JSON encoding, and lane test harness. Full upstream runtime parity remains dependency-gated on Poetry/Python setup, `pdftext`, `pypdfium2`/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Nougat, Streamlit/FastAPI/Uvicorn, tabulate printing fidelity, OCR/rendering helpers, and live benchmark workflow execution.

## Non-Overlap

This does not repeat accepted PDF parser/xref/image/font/security/table/outline/metadata behavior, benchmark scoring thresholds, table scoring verification, Markdown output artifact path derivation, MarkerApp Streamlit command planning, or marker server upload/remote API boundaries. The bounded behavior is only the final benchmark output artifact boundary from `benchmarks/overall.py`: JSON report persistence plus summary and score table rows.
