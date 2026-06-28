# ZIP Data Descriptor Byte Totals

This slice keeps shared ZIP/OPC package handling native and bounded while
summarizing data descriptor byte totals before DOCX/EPUB/ODF package readers
decide which entries may expose bytes.

- `ZipPackage::dataDescriptorPreflight()` and
  `ZipPackage::dataDescriptorIntegrityPreflight()` now report aggregate
  descriptor bytes, value bytes, descriptor span bytes, signed and unsigned
  descriptor bytes, surplus/truncated descriptor bytes, and the largest
  descriptor entry.
- `strictImportPreflight()` and `rawStrictImportPreflight()` inherit the same
  summaries through their existing `dataDescriptors` fields.
- The focused ZIP package test covers signed and unsigned descriptor fixtures
  without invoking external zip/unzip tooling.

This does not change descriptor parsing or byte exposure policy. It only rolls
up existing per-entry descriptor provenance for importer handoff review.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed with
  1 file, 5,717 assertions, and 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` remains red on the existing broad
  lane baseline: 295 files, 117,850 assertions, and 9,781 failures, with visible
  failures outside this ZIP/OPC slice in `YamlMetadataReviewTest.php`.
