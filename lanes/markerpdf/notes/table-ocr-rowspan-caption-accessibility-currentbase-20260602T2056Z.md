# table-ocr-rowspan-caption-accessibility-currentbase

## Source Truth

- Upstream `marker/tables/table.py::format_tables()` removes only intersecting `Table` blocks, inserts the recognized table at the original table position, and preserves surrounding `Section-header` and `Caption` blocks.
- Upstream `marker/postprocessors/markdown.py::block_surround()` renders `Section-header`, `Caption`, and `Table` as separate block types, so WordPress import needs explicit review metadata to bind the visible context back to the table.
- Locked `tabled-pdf` `SpanTableCell` preserves `row_ids` and `col_ids`, while `tabled/formats/markdown.py` and `html.py` format only anchor cells through `tabulate(..., headers="firstrow")`. Covered span occupancy and caption/header relationships therefore need native review metadata before Markdown/HTML formatting drops them.

## Patch

- `SuppliedDocumentConverter` now adds `metadata.table_section_caption_review[*].accessibility` for supplied/forced-OCR tables with stable `table_id`, optional `caption_id`, optional `section_id`, `aria_describedby`, `aria_labelledby`, `header_ids`, span counts, and `data_cell_headers`.
- Caption and section review rows now carry `caption_id`/`section_id` plus table linkage so a WordPress renderer can map the table to its preserved context without scraping adjacent Markdown.
- Added a focused converter test and WordPress smoke covering a rowspanned first-column header, grouped column header, row-1 subheaders, caption binding, `headers` references for body cells, and stale pdftext table-line exclusion.

## Verification

- `php -l lanes/markerpdf/src/SuppliedDocumentConverter.php`: no syntax errors.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-table-ocr-rowspan-caption-accessibility-currentbase.php`: no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`: 1 test file, 413 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-table-ocr-rowspan-caption-accessibility-currentbase.php`: passed with table/caption/section IDs, ARIA relationships, rowspanned header, body-cell header maps, stale-table exclusion, and native-only flags true.
- `php tools/run-tests.php lanes/markerpdf/tests/TableFormatterTest.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`: 3 test files, 720 assertions, 0 failures.
- `php -r 'foreach (["lanes/markerpdf/lane-status.json","lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, flags: JSON_THROW_ON_ERROR); echo "$f OK\n"; }'`: passed.
- `git diff --check -- lanes/markerpdf`: passed.

## Status Delta

- Focused PHP behavior tests: 811 -> 812 pass / 0 fail.
- WordPress scenarios: 811 -> 812.
- Manifest mapped behavior: 570 -> 571 / 78.

## Dependency Closure

No new support component is needed. The slice reuses the native supplied-document converter, table formatter, recognizer span-grid metadata, and forced-OCR fixture routing; live Python, tabled-pdf, OCR/model execution, pypdfium, Poppler, Ghostscript, and external PDF tools remain out of scope.

## Non-Overlap

This does not repeat accepted forced-OCR routing, merged-cell geometry, polygon geometry, grid-border assignment, rowspanned header-grid classification, or section/caption context preservation. The new behavior binds the existing span-grid/context reviews into stable accessibility IDs and data-cell header maps for WordPress table rendering.
