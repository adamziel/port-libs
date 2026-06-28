# DOCX OpenXML XML Text Node Provenance

2026-06-28 slice for `plib-pc6gl`.

`DocxOpenXmlReader` now carries metadata-only XML text-node provenance for XML-inspectable DOCX package parts. The package inventory and `packageProvenance.summary` report text-node counts, whitespace versus non-whitespace buckets, byte-length rollups, parent-path buckets, sorted part names, and CRC32/SHA-256 digests for individual text-node rows without exposing raw XML text or package bytes.

Focused validation:

```text
php -l lanes/pandoc/src/DocxOpenXmlReader.php
php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php
php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php lanes/pandoc/tests/DocxGeneratedFieldMetadataTest.php

1 test files, 10782 assertions, 0 failures
3 test files, 11165 assertions, 0 failures
```
