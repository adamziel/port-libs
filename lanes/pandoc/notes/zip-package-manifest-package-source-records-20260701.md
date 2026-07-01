# ZIP Package Manifest Package Source Records

`ZipPackage::packageManifestPreflight()` now carries package-level source
record provenance alongside entry-level local and central record hashes.

The manifest exposes metadata-only spans for:

- full archive length and SHA-256;
- central directory offset, byte count, end offset, and SHA-256;
- any bytes between the central directory and EOCD, including unverified
  central-directory digital signature records;
- EOCD offset, byte count, end offset, and SHA-256;
- package comment offset, byte count, presence flag, and SHA-256.

The deterministic `manifestSha256` input now includes this package source
object before the entry manifest rows. Constructed package preflight,
raw strict import preflight, and strict import preflight keep the same manifest
surface so DOCX/EPUB/ODT/OPC callers can compare package identity boundaries
without exposing package bytes.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  (4,938 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  (4,668 assertions, 0 failures)
