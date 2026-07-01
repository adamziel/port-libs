# DOCX/OpenXML printer settings package provenance

Work item: `plib-47ncz`

## Summary

`DocxOpenXmlReader` now inventories `printerSettings` relationships declared from the DOCX settings part as metadata-only package provenance. The reader records relationship ids, target paths, query and fragment suffixes, safe versus unsafe external target policy, content-type coverage, byte length, CRC32, and SHA-256 for local package parts.

Printer settings payload bytes remain blocked from AST attributes. The handoff exposes `printer-settings-bytes-blocked` and `printer-settings-metadata-only` policies, plus issue codes for missing targets, missing content types, unexpected content types, external printer settings, and unsafe external schemes.

The broad OPC package inventory now tags local printer settings relationship targets with the `printer-settings` role so relationship-type and part-role rollups can account for these sidecars alongside the existing generic settings relationship inventory.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`

Focused DOCX validation passed with 10,067 assertions and 0 failures.

Direct-format parity remains active in lane status; this slice extends native DOCX package ingestion without shelling out to Pandoc, office suites, unzip/zip, external validators, or other engines.
