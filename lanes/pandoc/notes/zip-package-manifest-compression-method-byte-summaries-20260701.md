# ZIP package manifest compression method byte summaries

Slice: `plib-wwh93`

`ZipPackage::packageManifestPreflight()` now carries deterministic aggregate
compression-method summaries in the metadata-only package manifest:

- entry, file, and directory counts per compression method;
- compressed and uncompressed byte totals per method;
- local-record byte totals per method;
- data-descriptor entry and byte totals per method.

The summaries are included in the manifest hash payload and therefore propagate
through strict and raw strict import preflights. This gives DOCX/EPUB/ODF and
OPC gates a bounded package-level view of stored versus deflated payload layout
without exposing package bytes.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed with
  1 file, 4,994 assertions, and 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with 1 file, 4,759 assertions, and 0 failures.
