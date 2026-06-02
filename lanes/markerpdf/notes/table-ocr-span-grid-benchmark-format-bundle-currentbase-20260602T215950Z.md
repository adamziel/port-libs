# table-ocr-span-grid-benchmark-format-bundle-currentbase

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `marker/benchmark/table.py` scores table Markdown by splitting pipe-cell rows, aligning each reference row to the best hypothesis row, and averaging cell similarity.
- Upstream `marker/tables/table.py::format_tables()` routes tabled recognition through `assign_rows_columns()` and `formatter("markdown", cell)[0]` before replacing only intersecting `Table` blocks.
- Locked tabled markdown formatting emits anchor cells through `tabulate(..., headers="firstrow")`, while `SpanTableCell` retains `row_ids` and `col_ids`; native WordPress review therefore needs the span grid and caption context attached before benchmark score rows are consumed.

## Patch

- Added `TableBenchmarkBundleBuilder`, which builds verifier-compatible table benchmark rows with native `TableScorer` semantics and attaches compact span-grid/context summaries to each row.
- Added conversion-derived bundling so supplied forced-OCR table Markdown can be extracted from native conversion output and paired with `metadata.table_spanning_grid_review` plus `metadata.table_section_caption_review`.
- Added `wordpress-table-ocr-span-grid-benchmark-format-bundle-currentbase.php`, a WordPress smoke that emits table benchmark score headers/rows, attached OCR span-grid metadata, stale-pdftext exclusion, and native-only execution flags.

## Verification

- `php -l lanes/markerpdf/src/TableBenchmarkBundleBuilder.php`: no syntax errors.
- `php -l lanes/markerpdf/tests/TableBenchmarkBundleBuilderTest.php`: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-table-ocr-span-grid-benchmark-format-bundle-currentbase.php`: no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/TableBenchmarkBundleBuilderTest.php`: 1 test file, 40 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/TableBenchmarkBundleBuilderTest.php lanes/markerpdf/tests/TableScorerTest.php lanes/markerpdf/tests/BenchmarkReportVerifierTest.php`: 3 test files, 64 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/TableFormatterTest.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/TableBenchmarkBundleBuilderTest.php lanes/markerpdf/tests/TableScorerTest.php lanes/markerpdf/tests/BenchmarkReportVerifierTest.php`: 6 test files, 857 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-table-ocr-span-grid-benchmark-format-bundle-currentbase.php`: emitted verifier-compatible table score JSON with score `1.0`, `rowspan+colspan+covered` span-grid flags, caption/section ids, stale pdftext exclusion, and native-only execution flags.
- `php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, flags: JSON_THROW_ON_ERROR); echo $f . " OK\n"; }'`: both JSON files OK.
- `git diff --check -- lanes/markerpdf`: passed.

## Status Delta

- Focused PHP behavior tests: expected `883 -> 887 pass / 0 fail` from the new focused test file's four PASS cases.
- WordPress scenarios: expected `883 -> 884`.
- Manifest mapped behavior: expected `623 -> 624 / 78`.

## Dependency Closure

No new support component is needed. This slice reuses the native supplied-document converter, table recognizer/formatter span-grid metadata, `TableScorer`, benchmark verifier, and lane test harness. Full upstream runner parity remains gated by live Python/model dependencies: `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf runtime models, Texify, OCR/PIL/Streamlit/FastAPI paths, benchmark model downloads, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted forced-OCR routing, merged-cell geometry, OCR polygon assignment, grid-border conflict review, rowspanned caption accessibility, or header-grid caption cellspan metadata. The new behavior bundles already produced OCR span-grid/context review into upstream table benchmark score rows so WordPress quality gates can verify scores without losing row/column-span provenance.
