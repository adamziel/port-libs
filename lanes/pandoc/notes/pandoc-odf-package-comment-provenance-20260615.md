# Pandoc ODF Package Comment Provenance

Slice: `pandoc-odf-package-comment-provenance-20260615`

Implemented bounded native PHP ODF/ODT package-ingestion provenance for ZIP package comments and central-directory entry comments.

`OpenDocumentPackage` and `OdfReader` now reuse `ZipPackage::commentPreflight()` in compact package inventory and rich import-report/document package provenance. The handoff exposes package comment presence, entry comment counts/names, and per-part comment text, raw length, encoding, and issue lists while keeping comments metadata-only. Media byte exposure and manifest-declared package part policy are unchanged.

Focused coverage:

- `OpenDocumentPackageTest.php`: compact package inventory reports package and entry comments without adding media handoff.
- `OdfReaderTest.php`: rich import-report/document `packageProvenance` reports the same comment metadata.

Verification:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php` -> 2 files, 6504 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfOdtShipReadinessStatusTest.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php lanes/pandoc/tests/OpenDocumentReaderTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php` -> 5 files, 6804 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 46 files, 89923 assertions, 0 failures

Accounting:

- `phpPass`: 3764 -> 3765
- `phpFail`: 0
- mapped upstream manifest cases: 3780 -> 3781
- `mappedOdfPackageCommentProvenanceCases`: 1
- `odfPackageCommentProvenanceAssertions`: 48
