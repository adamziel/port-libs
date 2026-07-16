# XLSX data validation provenance

Work item: `plib-e1gbt`

## Summary

`XlsxReader` now preserves bounded worksheet data-validation metadata for review
packets. Workbook-level XLSX metadata reports the data-validation policy, total
validation count, sheet count, and range count. Per-sheet review metadata now
includes declared and parsed validation counts, range rollups, type counts,
diagnostics, and compact validation records.

Validation records capture option flags, operators, error style, IME mode, and
target ranges. Formula and prompt/error message payloads are not emitted as raw
text; the review packet records presence, byte length, and SHA-256 digests so
callers can detect provenance without evaluating formulas or leaking list/message
contents into rendered output.

## Validation

- `php -l lanes/pandoc/src/XlsxReader.php`
- `php -l lanes/pandoc/tests/XlsxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XlsxReaderTest.php`

Focused XLSX validation passed with 1 file, 577 assertions, and 0 failures.

No Pandoc binary, office suite, external validator, unzip/zip command, browser
engine, TeX engine, Jupyter, or Node tooling was invoked.
