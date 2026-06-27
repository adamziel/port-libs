# DOCX package XML comment provenance

Slice: `plib-616hg`

## Scope

`DocxOpenXmlReader` now carries metadata-only XML comment provenance for every XML-inspectable DOCX package part through `packageProvenance`.

The reader records comment counts, aggregate byte lengths, parent element paths, CRC32/SHA-256 digests, and per-part comment rows. It does not expose raw comment text, package bytes, or external targets.

## Accounting

- `lane-status.json` `phpPass`: `464 -> 465`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2307 -> 2308`
- `mappedDocxPackageXmlCommentProvenanceCases`: `1`
- `docxPackageXmlCommentProvenanceAssertions`: `28`

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1 test files, 10557 assertions, 0 failures`

No Pandoc binary, office suite, unzip/zip CLI, browser, external validator, or network fetch was used.
