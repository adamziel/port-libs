# DOCX/OpenXML package XML processing-instruction pseudo-attribute provenance

Slice: `plib-oaoh6`
Base: `89949a7748`

## Behavior

- `DocxOpenXmlReader` now extracts XML processing-instruction pseudo-attributes from DOCX/OpenXML package XML parts.
- Per-instruction metadata includes sorted pseudo-attribute names plus value byte length, CRC32, and SHA-256 for each unique attribute name in the instruction.
- Per-part and package-summary rollups expose pseudo-attribute counts, name buckets, sorted names, and total value byte lengths.
- Raw processing-instruction data and raw pseudo-attribute values remain excluded from package metadata.

## Evidence

```text
php -l lanes/pandoc/src/DocxOpenXmlReader.php
php -l lanes/pandoc/tests/DocxOpenXmlPackageXmlProcessingInstructionPseudoAttributeTest.php
php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageXmlProcessingInstructionPseudoAttributeTest.php
1 test files, 40 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageXmlProcessingInstructionPseudoAttributeTest.php lanes/pandoc/tests/DocxOpenXmlPackageXmlProcessingInstructionProvenanceTest.php lanes/pandoc/tests/DocxOpenXmlPackageXmlCommentProvenanceTest.php
3 test files, 107 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php
1 test files, 12508 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/DocxOpenXml*.php
78 test files, 17008 assertions, 0 failures

jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json
git diff --check -- lanes/pandoc
```

## Delta

- Added one focused DOCX/OpenXML package-ingestion PHP PASS case.
- Added 40 focused DOCX package XML processing-instruction pseudo-attribute assertions.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2883 -> 2884`.
- `mappedDocxPackageXmlProcessingInstructionPseudoAttributeCases`: `1`.
- `docxPackageXmlProcessingInstructionPseudoAttributeAssertions`: `40`.

## Non-Overlap

This does not repeat DOCX XML CDATA, XML comment, XML processing-instruction target/provenance, content-type parameter, relationship target/source, ZIP source-record, or package path identity slices. It only adds metadata-only pseudo-attribute name/value-hash rollups for processing-instruction data in XML package parts.

No Pandoc, office suite, TeX/browser engine, unzip/zip command, external validator, Node, or live service was invoked.
