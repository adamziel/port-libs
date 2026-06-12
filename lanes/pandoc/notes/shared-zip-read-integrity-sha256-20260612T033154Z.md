# Shared ZIP read-integrity payload fingerprints

## Summary

This slice extends the shared `ZipPackage::readIntegrityPreflight()` report with
per-entry `contentSha256` payload fingerprints for successfully readable package
entries. The existing CRC32, byte-count, readability, and failure behavior is
unchanged; unreadable entries now carry `contentSha256 => null` in both the
entry row and failed-entry row.

This gives DOCX, EPUB, ODT, and other Pandoc package readers a full-package
payload fingerprint surface without requiring selected-entry handoff first.

## Scope

- Added readable-entry SHA-256 provenance to `lanes/pandoc/src/ZipPackage.php`.
- Pinned focused ZIP coverage for deflated files, stored files, empty directory
  entries, and corrupt payload failures in `lanes/pandoc/tests/ZipPackageTest.php`.
- No external ZIP tools, office suites, Pandoc runners, validators, browser
  engines, or online services were used.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`
