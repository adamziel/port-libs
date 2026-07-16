# ODF table-cell data-style handoff

Slice: `pandoc-odf-open-document-core-current-base-20260609T050342Z`
Base: `61b62145a7f933d909276d2f3ea5cabaaa6f0b71`
Date: 2026-06-09 UTC

## Behavior

This slice preserves the ODF table-cell style `style:data-style-name` attribute as review metadata. `OdfReader` now records the value on parsed style definitions and promotes it from table-cell styles into:

- AST cell attribute `odfCellDataStyleName`
- WordPress/table HTML attribute `data-odf-cell-data-style-name`
- CSS class `odf-table-cell-data-style`
- table geometry `sourceAttributes`
- import report counter `content.tableDataStyledCellCount`

The implementation is intentionally metadata-only. It does not implement number/date/currency format grammar or formatted display value evaluation.

## Evidence

Red-first focused test before implementation:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: `1 test files, 2995 assertions, 1 failures`

Final focused verification:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: `1 test files, 3021 assertions, 0 failures`

Example smoke:

`php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`

Result: `odf open document handoff self-test ok`

Additional checks:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`
- JSON validation for `lanes/pandoc/lane-status.json`
- JSON validation for `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check -- lanes/pandoc`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP ODF style parsing, table-cell AST attributes, table geometry source attributes, and WordPress block serialization.

## Non-Overlap

This does not overlap prior ODF support for named expressions, data pilots, table tracked changes, database ranges, subtotal rules, cell detective metadata, covered cells, drop-down fields, field spans, PDF engine handoff, DOCX parsing, or EPUB packaging. A logical follow-up is bounded ODF number/date/currency style grammar, if the lane needs display-format evaluation rather than source provenance.
