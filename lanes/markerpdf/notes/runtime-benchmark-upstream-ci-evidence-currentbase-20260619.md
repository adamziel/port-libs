# Runtime Benchmark Upstream CI Evidence Current Base

Slice: `runtime-benchmark-upstream-ci-evidence-currentbase`
Issue: `plib-tuzwg.17`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` runs the short CI benchmark archive `benchmark_data_short.zip` and verifies marker scores for `multicolcnn.pdf` and `switch_trans.pdf`.

The native PHP lane already replayed the benchmark loop and verifier thresholds. This slice closes the accounting gap by making that replay evidence explicit:

- archive filename, SHA-256, download id, and workflow source
- upstream document, PDF path, reference path, committed marker example path, and source commit
- native marker score and upstream threshold for each replayed CI document
- explicit no-execution flags and heavy-runtime exclusions

## Exclusions

The evidence intentionally excludes live Python process execution, `pdftext`, `pypdfium2`/PDFium, Surya/Torch OCR/layout/table/recognition models, `tabled-pdf`, Texify, Nougat, Streamlit/FastAPI/Uvicorn runtimes, external OCR, raster rendering, and external PDF validation tools.

## Verification

Targeted gate:

```bash
php tools/run-tests.php lanes/markerpdf/tests/BenchmarkReportVerifierTest.php lanes/markerpdf/tests/BenchmarkRunnerTest.php lanes/markerpdf/tests/BenchmarkReportScoreVerifierCurrentBaseTest.php
php lanes/markerpdf/examples/wordpress-benchmark-runner.php
```

Expected WordPress smoke evidence includes `upstream_fixture_evidence.mapped_native_fixture_count=2`, `passes_upstream_ci_marker_thresholds=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
