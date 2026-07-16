# XLSX sheet view pane provenance

Work item: `plib-e1gbt`

## Summary

`XlsxReader` now preserves worksheet view metadata for review packets. Sheet
reviews include bounded `sheetViews` records with workbook view id, view mode,
top-left cell, grid/header/zero/right-to-left flags, zoom settings, pane state,
and selection ranges. Workbook-level XLSX metadata also reports sheet view,
frozen pane, and split pane counts.

This helps reviewers see freeze/split-pane context for hidden, filtered, or
wide worksheets without changing rendered table output or evaluating filters.

## Validation

- `php -l lanes/pandoc/src/XlsxReader.php`
- `php -l lanes/pandoc/tests/XlsxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XlsxReaderTest.php`
  - 1 file, 481 assertions, 0 failures

No Pandoc binary, office suite, external validator, unzip/zip command, browser
engine, TeX engine, Jupyter, or Node tooling was invoked.
