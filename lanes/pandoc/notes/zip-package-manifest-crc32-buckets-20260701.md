# ZIP Package Manifest CRC32 Buckets

Slice: `plib-vch38` on 2026-07-01.

`ZipPackage::packageManifestPreflight()` now reports metadata-only CRC32 value buckets for whole-package shared ZIP/OPC review:

- `crc32ValueCount`, `duplicateCrc32ValueCount`, `duplicateCrc32EntryCount`, and `zeroCrc32EntryCount`.
- `crc32ValueSummaries` and `duplicateCrc32ValueSummaries` with per-value entry counts, zero-CRC flags, central-directory indexes, local-header orders, directory roots, parent directories, extension keys, package-part kinds, byte totals, roles, and entry names.

The new surface is outside the `zip-package-manifest-v1` hash payload, so existing manifest identity stays stable while DOCX/EPUB/ODF and OPC callers can review repeated or empty payload declarations before reader handoff. The slice does not expose payload bytes and did not invoke Pandoc, office suites, TeX/browser engines, Typst, Jupyter, Node, `zip`/`unzip`, external validators, or live services.

Focused validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test file, 6303 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> `1 test file, 4916 assertions, 0 failures`
