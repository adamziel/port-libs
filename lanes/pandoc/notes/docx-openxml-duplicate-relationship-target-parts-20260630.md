# DOCX OpenXML Duplicate Relationship Target Parts

Slice: `plib-cnn75` DOCX/OpenXML package ingestion review metadata.

`DocxOpenXmlReader` now summarizes exact duplicate internal relationship
target parts across all DOCX relationship sidecars. The package provenance
summary exposes duplicate target-part counts, relationship counts, target-part
names, and compact groups with source parts, relationship parts, relationship
ids, target suffixes, content types, and existing/missing target counts.

This is metadata-only package ingestion. It does not expose target bytes and
does not invoke Pandoc, office suites, external validators, unzip/zip, Node, or
browser tooling.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed: 1 file, 9,984 assertions, 0 failures
- Post-rebase full lane gate `php tools/run-tests.php lanes/pandoc/tests`
  exited red with 303 files, 118,748 assertions, and 9,634 failures in
  unrelated baseline suites including MarkdownReader, UnicodeText, and
  YamlMetadataReview; the touched DOCX OpenXML focused test passed inside the
  run.

Direct-format parity accounting remains active for the DOCX/OpenXML lane; this
slice adds one focused package-ingestion PASS case without changing the format
denominator.
