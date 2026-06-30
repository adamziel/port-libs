# DOCX OpenXML relationship target case-fold basename provenance

Slice: plib-1ucyo

DocxOpenXmlReader now summarizes internal relationship target basenames after case folding. This keeps OPC package target audit metadata visible when relationship targets differ only by case, including per-bucket case variants, existing/missing target counts, content-type sources, relationship ids, target parts, and largest existing target digests.

The change is metadata-only:

- External relationship targets remain outside package target basename buckets and are still governed by the existing external-target policy summaries.
- Existing target byte exposure remains limited to byte counts and CRC32/SHA-256 digests already used by package provenance.
- No document rendering path or direct DOCX body conversion behavior changes.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed with 1 file, 14776 assertions, 0 failures.
