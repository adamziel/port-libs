# XLSX image anchor position provenance

Work item: `plib-e1gbt`

## Summary

`XlsxReader` now deepens image media provenance for DrawingML anchors. Image
feature metadata still reports media byte size, SHA-256, image dimensions, and
relationship references, and now each matching drawing anchor also carries:

- target part/query/fragment context from the image relationship;
- zero-based marker metadata plus derived worksheet cell references;
- one-cell/absolute anchor extents in EMUs and normalized pixels;
- absolute anchor positions in EMUs and normalized pixels.

This keeps image payload bytes blocked from AST output and does not render images
into Markdown/HTML/WordPress output. It only improves bounded review metadata for
XLSX package ingestion.

## Validation

- `php -l lanes/pandoc/src/XlsxReader.php`
- `php -l lanes/pandoc/tests/XlsxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XlsxReaderTest.php`

Focused XLSX validation passed with 1 file, 300 assertions, and 0 failures.

No Pandoc binary, office suite, external validator, unzip/zip command, browser
engine, TeX engine, Jupyter, or Node tooling was invoked.
