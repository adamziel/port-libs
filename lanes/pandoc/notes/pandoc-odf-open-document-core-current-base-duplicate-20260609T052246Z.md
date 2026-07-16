# Pandoc ODF OpenDocument Row Groups Handoff

Slice: `pandoc-odf-open-document-core-current-base-duplicate-20260609T052246Z`
Base accepted HEAD: `aeac7627505caef0c7f45b74c533b70ec36e1807`

## Behavior

- `OdfReader` now preserves ODF spreadsheet-style table row grouping:
  - `table:table-rows` children are imported as body rows in the existing `table_body` section.
  - `table:table-footer-rows` children are imported as a `table_foot` section.
- The existing table model, `TableGeometry`, Markdown writer, and WordPress writer already supported `table_foot`; this slice only wires the ODF reader into that native table-section contract.
- Added a WordPress handoff smoke that verifies grouped body rows render under `<tbody>` and footer rows render under `<tfoot>`.

## Evidence

- `php -l lanes/pandoc/src/OdfReader.php`
  - `No syntax errors detected in lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/OdfReaderTest.php`
- `php -l lanes/pandoc/examples/wordpress-odf-table-row-groups-handoff.php`
  - `No syntax errors detected in lanes/pandoc/examples/wordpress-odf-table-row-groups-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 3037 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-odf-table-row-groups-handoff.php --self-test`
  - `odf table row groups handoff self-test ok`

## Status Delta

- `phpPass`: `2368 -> 2369`
- `benchmarkDenominator.mapped`: `2762 -> 2763`
- Focused ODF test growth: `+1` PASS case and `+16` assertions.

## Dependency Closure

No new support component is needed. This reuses the native PHP ODF package reader, in-memory `ZipPackage` fixtures, `TableGeometry` section metadata, `WordPressBlockWriter` table-section rendering, and focused PHP test runner. Full upstream Pandoc ODT runner parity remains a separate upstream-runner dependency task that would require hydrated pinned upstream sources and Haskell test executables.

## Non-Overlap

This does not repeat the accepted ODF field work for drop-downs, page variables, chapter/file/statistic fields, database fields, form controls, captions, styles, lists, or metadata declarations. It targets only ODF table row wrapper sections that were previously skipped by the ODF reader.
