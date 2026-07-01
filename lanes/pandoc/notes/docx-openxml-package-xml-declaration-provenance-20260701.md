# DOCX OpenXML Package XML Declaration Provenance - 2026-07-01

Slice: `plib-7jqg4`, DOCX/OpenXML package ingestion core blocker.

## Summary

`DocxOpenXmlReader` now carries metadata-only XML declaration provenance for
all XML-like DOCX package parts. Package inventory records whether each XML-like
part was checked, whether an XML declaration is present, and the declaration
version, encoding, standalone flag, and attribute count.

`packageProvenance.summary` now rolls those records up into checked/present/
missing part counts, declaration part-name lists, missing-declaration part-name
lists, version buckets, encoding buckets, standalone yes/no/omitted buckets, and
bounded declaration records. The scan is prolog-only, so malformed XML parts can
still contribute declaration metadata without aborting package ingestion.

The records do not expose raw XML bytes, root text, or declaration source text.
Non-XML package parts remain out of the declaration scan.

Direct-format parity remains active in blocker notes; this slice is bounded to
native PHP DOCX/OpenXML package provenance.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlPackageXmlDeclarationProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageXmlDeclarationProvenanceTest.php`
  - `1` file, `52` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageXmlDeclarationProvenanceTest.php lanes/pandoc/tests/DocxOpenXmlPackageXmlCommentProvenanceTest.php lanes/pandoc/tests/DocxOpenXmlPackageXmlProcessingInstructionProvenanceTest.php lanes/pandoc/tests/DocxOpenXmlSelectedXmlRootByteAggregateTest.php`
  - `4` files, `157` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1` file, `12508` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXml*.php`
  - `78` files, `17020` assertions, `0` failures

No external Pandoc, office suite, TeX/browser engine, Typst, Jupyter, Node,
zip/unzip, external validators, or live services were invoked.
