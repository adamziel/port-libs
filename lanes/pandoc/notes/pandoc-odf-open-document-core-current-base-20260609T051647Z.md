# ODF/OpenDocument data-style grammar handoff

Slice: `pandoc-odf-open-document-core-current-base-20260609T051647Z`
Base accepted HEAD: `90a8c08774000b674162bc7fd29c3796a2cc07c5`
Date: 2026-06-09 UTC

## Behavior

This slice adds bounded native PHP extraction for ODF/OpenDocument
`number:*` data-style definitions used by `style:data-style-name`.

- Preserves `number-style`, `currency-style`, `percentage-style`,
  `date-style`, `time-style`, `boolean-style`, and `text-style`
  definitions from automatic and named style roots.
- Records data-style type, display name, component list, component count, and
  a stable format signature in `dataStyles`.
- Resolves table-cell style `dataStyleName` references into cell AST
  metadata and WordPress/table HTML attributes:
  `data-odf-cell-data-style-type`,
  `data-odf-cell-data-style-component-count`, and
  `data-odf-cell-data-style-signature`.
- Reports `content.tableDataStyleDefinitionCellCount`,
  `styles.dataStyleCount`, and `styles.dataStyles` in the import report.

The implementation remains a source-provenance mapping. It does not evaluate
or localize number/date/currency display values.

## Evidence

Red-first focused check before implementation:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: `1 test files, 3021 assertions, 1 failures`; the new data-style
grammar test failed because `dataStyles` was not populated.

Final focused verification:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: `1 test files, 3056 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`

Result: `odf open document handoff self-test ok`.

Additional checks:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`
- JSON validation for `lanes/pandoc/lane-status.json`
- JSON validation for `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check -- lanes/pandoc`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP DOM parsing,
the existing ODF style catalog, table-cell AST metadata, table geometry source
attributes, and WordPress block serialization. No Pandoc, Haskell runner,
Word, LibreOffice, zip/unzip, TeX/PDF engine, external template engine,
online service, live provider test, or live-service provider test was run.

## Non-Overlap

This builds directly on the previous table-cell `style:data-style-name`
provenance slice without repeating it. It does not overlap prior ODF support
for named expressions, data pilots, table tracked changes, database ranges,
subtotal rules, cell detective metadata, covered cells, drop-down fields,
field spans, PDF engine handoff, DOCX parsing, or EPUB packaging.

## Follow-Up

If later conversion needs rendered text parity, add a separate bounded
formatter that applies the captured signatures to numeric/date/currency source
values. That evaluation layer is intentionally out of scope here.
