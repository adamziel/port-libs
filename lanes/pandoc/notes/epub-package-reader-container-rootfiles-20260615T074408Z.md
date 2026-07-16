# EPUB package reader container rootfiles

Slice: plib-78xlv, Pandoc EPUB3 package ingestion core blocker, 2026-06-15.

Direct directory EPUB package ingestion now reports OCF container rootfile alternatives on the document `epub` attributes. The report keeps the selected OPF rootfile, selected index, all rootfile rows, OPF/alternate/missing counts, media-type parameter normalization, query/fragment suffix preservation, existence diagnostics, language/direction, and custom authoring attributes.

Verification:

- `php -l lanes/pandoc/src/EpubPackageReader.php`
- `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php` - 1 file, 1243 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 46 files, 87123 assertions, 0 failures

No Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.
