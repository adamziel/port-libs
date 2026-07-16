# XLSX style color and table filter provenance

Work item: `plib-e1gbt`

## Summary

`XlsxReader` now preserves richer style color provenance for XLSX style records.
Font, fill, and border color attributes continue to expose their compact color
tokens, and now also include bounded metadata describing the source attribute,
raw value, normalized RGB/ARGB components where available, indexed/theme integer
ids, auto-color booleans, and tint values.

Table-part metadata now also includes small rollups derived from the existing
table parser: column-name lists plus compact auto-filter column count/list
fields. The detailed filter model remains in `autoFilter`, and no formulas or
filters are evaluated while reading worksheet rows.

## Validation

- `php -l lanes/pandoc/src/XlsxReader.php`
- `php -l lanes/pandoc/tests/XlsxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XlsxReaderTest.php`

Focused XLSX validation passed with 1 file, 365 assertions, and 0 failures.

No Pandoc binary, office suite, external validator, unzip/zip command, browser
engine, TeX engine, Jupyter, or Node tooling was invoked.
