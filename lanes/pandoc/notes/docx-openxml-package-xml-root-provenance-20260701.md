# DOCX OpenXML Package XML Root Provenance - 2026-07-01

Slice: `plib-e70g4`, DOCX/OpenXML package ingestion core blocker.

## Summary

`DocxOpenXmlReader` now carries metadata-only XML root element provenance for
all XML-like package parts in package inventory and `packageProvenance.summary`.
The new package review fields expose per-part root validity, namespace URI,
local/qualified names, prefix, non-namespace root attribute count, namespace
declaration count/prefixes, invalid XML part names, and aggregate root
name/namespace/prefix buckets.

The records intentionally omit raw XML bytes, root text, and root attribute
values. Non-XML package parts remain out of the root scan, and invalid XML parts
are counted with bounded parse status instead of aborting ingestion.

Direct-format parity remains active in blocker notes; this slice is bounded to
native PHP DOCX/OpenXML package provenance.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlPackageXmlRootProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageXmlRootProvenanceTest.php`
  - `1` file, `50` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageXmlRootProvenanceTest.php lanes/pandoc/tests/DocxOpenXmlPackageXmlCommentProvenanceTest.php lanes/pandoc/tests/DocxOpenXmlPackageXmlProcessingInstructionProvenanceTest.php lanes/pandoc/tests/DocxOpenXmlSelectedXmlRootByteAggregateTest.php`
  - `4` files, `155` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1` file, `12508` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXml*.php`
  - `78` files, `17018` assertions, `0` failures

No external Pandoc, office suite, TeX/browser engine, Typst, Jupyter, Node,
zip/unzip, external validators, or live services were invoked.
