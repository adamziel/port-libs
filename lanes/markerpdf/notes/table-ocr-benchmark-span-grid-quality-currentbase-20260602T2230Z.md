# table-ocr-benchmark-span-grid-quality-currentbase

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes recognized tables through `marker/tables/table.py::format_tables()` before benchmark scoring.
- Locked `tabled-pdf==0.1.4` source at `/tmp/markerpdf-tabled-src/tabled/schema.py` defines `SpanTableCell.row_ids` and `col_ids`, while `/tmp/markerpdf-tabled-src/tabled/assignment.py::handle_rowcol_spans()` expands row/column ids only through contiguous open bands.
- `/tmp/markerpdf-tabled-src/tabled/formats/markdown.py::markdown_format()` feeds only each cell anchor row/column to `tabulate(..., headers="firstrow")`, so native benchmark artifacts need explicit span-grid quality metadata before WordPress quality gates trust a flattened Markdown table score.

## Patch

- `TableBenchmarkBundleBuilder` now adds `table_ocr_span_grid_quality` metadata to each span-grid summary.
- The quality summary records expected grid-cell count, anchor/empty counts, missing/duplicate grid positions, orphan covered cells, non-contiguous spans, covered-cell/span consistency, and stable `quality_flags`.
- `outputTables()` now exposes a `Span quality` column alongside the existing upstream-compatible table score row fields.
- `wordpress-table-ocr-span-grid-benchmark-format-bundle-currentbase.php` now emits `passes_span_grid_quality_gate=true` for a complete forced-OCR span grid.

## Verification

- `php -l lanes/markerpdf/src/TableBenchmarkBundleBuilder.php`: no syntax errors.
- `php -l lanes/markerpdf/tests/TableBenchmarkBundleBuilderTest.php`: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-table-ocr-span-grid-benchmark-format-bundle-currentbase.php`: no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/TableBenchmarkBundleBuilderTest.php`: 1 test file, 59 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/TableFormatterTest.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/TableBenchmarkBundleBuilderTest.php lanes/markerpdf/tests/TableScorerTest.php lanes/markerpdf/tests/BenchmarkReportVerifierTest.php`: 6 test files, 876 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-table-ocr-span-grid-benchmark-format-bundle-currentbase.php`: emitted `Span quality` as `complete_grid+contiguous_spans+resolved_covered_cells` and `passes_span_grid_quality_gate=true`.
- `php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, flags: JSON_THROW_ON_ERROR); echo $f . " OK\n"; }'`: both lane JSON files OK.
- `git diff --check -- lanes/markerpdf`: passed.

## Status Delta

- Focused PHP behavior tests: `907 -> 908 pass / 0 fail`.
- Mapped semantics: `638 -> 639 / 78`.
- WordPress scenario count: `907 -> 908`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native table recognizer span-grid review, supplied-document conversion metadata, table scorer, benchmark verifier, and WordPress smoke path. Full upstream runner parity remains dependency-gated by Poetry plus `pdftext`, `pypdfium2`/PDFium, Surya/Torch models, `tabled-pdf`, Texify, OCR/PIL/Streamlit/FastAPI runtime paths, benchmark model downloads, and external rendering/OCR helpers.

## Non-Overlap

This does not repeat accepted forced-OCR routing, OCR prediction unwrapping, multiline header folding, merged-cell geometry, grid-border conflict review, rowspanned caption accessibility, or the earlier span-grid benchmark bundle. The new behavior is specifically benchmark-row quality review for complete, contiguous, and resolved span-grid occupancy.
