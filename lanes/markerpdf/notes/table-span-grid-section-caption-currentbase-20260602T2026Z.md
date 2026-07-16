# table-span-grid-section-caption-currentbase

## Source Truth

- Upstream `marker/tables/table.py::format_tables()` removes intersecting `Table` layout blocks and inserts the recognized table at the original insertion point while preserving surrounding non-table blocks.
- Upstream `marker/postprocessors/markdown.py` renders `Section-header`, `Caption`, and `Table` as separate Markdown block types, so section/caption context must stay adjacent to the replacement table instead of being folded into table text.
- Locked `tabled-pdf` schema/formatters preserve `SpanTableCell::row_ids` and `col_ids`, while Markdown/HTML formatting consumes the anchor grid only; native review metadata must retain rowspan/colspan occupancy before formatting drops covered span slots.

## Patch

- `TableFormatter::formatTables()` now emits `table_context_reviews` for each inserted table, including matched stale table block indexes, insertion point, nearest section/title, nearest caption, and `review_target=table_span_grid`.
- `SuppliedDocumentConverter` now exposes `metadata.table_section_caption_review`, joining the new context review with existing `table_spanning_grid_review` summaries for rows, columns, rendered cells, header ids, and span flags.
- Added a WordPress smoke that renders a section heading, caption, colspan/rowspan table cells, and `headers` relationships while proving stale pdftext table rows stay excluded.

## Verification

- `php -l lanes/markerpdf/src/TableFormatter.php`: no syntax errors.
- `php -l lanes/markerpdf/src/SuppliedDocumentConverter.php`: no syntax errors.
- `php -l lanes/markerpdf/tests/TableFormatterTest.php`: no syntax errors.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-table-span-grid-section-caption-currentbase.php`: no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/TableFormatterTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`: 2 test files, 434 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/TableFormatterTest.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`: 3 test files, 693 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-table-span-grid-section-caption-currentbase.php`: passed with section heading, caption, colspan, rowspan, table-span-grid mapping, and stale pdftext exclusion flags all true.
- `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json`: passed.
- `git diff --check -- lanes/markerpdf`: passed.

## Status Delta

- Focused PHP behavior tests: 781 -> 783 pass / 0 fail.
- WordPress scenarios: 781 -> 782.
- Manifest mapped behavior: 555 -> 556 / 78; table-formatting functions mapped 2 -> 3.

## Dependency Closure

No new support component is needed. The slice reuses the native supplied-document, table recognizer, and table formatter helpers; Python/model/table OCR execution remains represented by supplied fixtures and local PHP review metadata only.

## Non-Overlap

This does not repeat accepted table grid-border assignment, polygon geometry, merged-cell header-axis, or merged-header-grid work. It maps the current-base boundary that binds span-grid review metadata to the preserved Section-header and Caption blocks around the table replacement.
