# ODF/OpenDocument quoted range-token handoff

Slice: `pandoc-odf-open-document-core-current-base-20260609T054801Z`
Base accepted HEAD: `2c84ca27878846c6b3725d422a6af783d4bbe9c7`
Date: 2026-06-09 UTC

## Behavior

This slice adds bounded native PHP tokenization for ODF/OpenDocument
whitespace-separated range lists that may contain quoted sheet names with
spaces.

- Preserves quoted sheet-name tokens in `table:print-ranges`.
- Preserves quoted sheet-name tokens in `table:scenario-ranges`.
- Preserves quoted sheet-name tokens in consolidation
  `table:source-cell-range-addresses`.
- Handles doubled apostrophes inside quoted sheet names, such as
  `'Source Team''s Sheet'.A1:'Source Team''s Sheet'.B5`.
- Keeps the handoff metadata-only: no spreadsheet range evaluation, formula
  execution, or office-suite behavior is introduced.

The parsed range tokens continue to feed AST metadata, import-report counts,
and WordPress `data-odf-*` attributes.

## Evidence

Baseline focused check before this patch:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: `1 test files, 3072 assertions, 0 failures`.

Red-first focused check before implementation:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: `1 test files, 3075 assertions, 1 failures`; the new quoted
range-token test failed because the old whitespace split broke
`'Review Sheet'.A1:'Review Sheet'.B2` into multiple tokens.

Final focused verification:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: `1 test files, 3094 assertions, 0 failures`.

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
the existing ODF table/consolidation metadata path, package fixtures, and
WordPress block serialization. No Pandoc, Haskell runner, Cabal command, Word,
LibreOffice, zip/unzip, external converter, external template engine,
TeX/PDF engine, browser renderer, online service, live provider test, or
live-service provider test was run.

## Non-Overlap

This builds on prior ODF table print-range, scenario, and consolidation
metadata extraction without repeating data-style grammar, named expression,
data-pilot, tracked-change, database-range, subtotal, detective, covered-cell,
drop-down field, field-span, DOCX, EPUB, PDF handoff, or XML/HTML5 DOM slices.

## Follow-Up

If later conversion needs spreadsheet-coordinate semantics, add a separate
bounded parser for ODF cell/range addresses. That evaluation layer is
intentionally out of scope for this metadata-preservation slice.
