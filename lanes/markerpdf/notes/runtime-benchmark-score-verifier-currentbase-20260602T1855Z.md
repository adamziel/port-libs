# Runtime Benchmark Score Verifier Current Base

Slice: `runtime-benchmark-score-verifier-currentbase`
Session: `port-dev-markerpdf-runtime39pdf-20260602T1855Z`
Base: `28240b72b0f77821c5ac2cf978b4d8bf8469270e`

## Source Truth

- Upstream `scripts/verify_benchmark_scores.py` at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` loads a JSON file path, dispatches by `--type marker|table`, checks `marker.files.multicolcnn.pdf.score > 0.34`, checks `marker.files.switch_trans.pdf.score > 0.40`, and checks table-row average score `>= 0.7`.
- This slice keeps the same score-threshold boundary while avoiding Python process execution, model downloads, PDF rendering, OCR, and external PDF tools.

## Behavior

- `BenchmarkReportVerifier::verifyScoreFile()` now reads upstream-shaped JSON score files, validates JSON decoding, and dispatches to the existing native marker/table checks.
- Marker score files return decoded report data after verifying both required document scores clear the upstream strict `>` thresholds.
- Table score files return decoded row data after verifying the average score clears the upstream `>= 0.7` boundary.
- Missing files, invalid JSON, scalar JSON roots, unsupported verifier types, threshold misses, and malformed row/report shapes fail before a WordPress quality gate trusts the score file.

## WordPress Path

`examples/wordpress-benchmark-score-verifier-currentbase.php` writes upstream-shaped marker and table score JSON files into temporary storage, verifies them through the native score-file boundary, and emits non-execution flags for WordPress import review.

## Verification

- `php -l lanes/markerpdf/src/BenchmarkReportVerifier.php`
  - passed
- `php -l lanes/markerpdf/tests/BenchmarkReportScoreVerifierCurrentBaseTest.php`
  - passed
- `php -l lanes/markerpdf/examples/wordpress-benchmark-score-verifier-currentbase.php`
  - passed
- `php tools/run-tests.php lanes/markerpdf/tests/BenchmarkReportScoreVerifierCurrentBaseTest.php`
  - passed, `1 test files, 11 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/BenchmarkReportScoreVerifierCurrentBaseTest.php lanes/markerpdf/tests/BenchmarkReportVerifierTest.php lanes/markerpdf/tests/BenchmarkReportBuilderTest.php lanes/markerpdf/tests/BenchmarkRunnerTest.php`
  - passed, `4 test files, 94 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-benchmark-score-verifier-currentbase.php`
  - passed; emitted `marker_score_file_verified=true`, `table_score_file_verified=true`, `marker_documents=["multicolcnn.pdf","switch_trans.pdf"]`, `table_rows=3`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`
- `git diff --check -- lanes/markerpdf`
  - passed

## Dependency Closure

No new support component is needed. This reuses the native benchmark report verifier, PHP JSON decoding, committed threshold metadata, and WordPress smoke path. Full upstream runner parity remains dependency-gated by Poetry plus `pdftext`, `pypdfium2`/PDFium, Surya/Torch model downloads, `tabled-pdf`, Texify, Nougat execution, Streamlit/FastAPI runtime paths, benchmark downloads, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat the accepted runtime benchmark API callback or benchmark output table/file writer slices. The new behavior is specifically the score-verifier JSON file path and `marker`/`table` dispatch boundary from upstream `scripts/verify_benchmark_scores.py`.
