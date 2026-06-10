# Shared ZIP/OPC Split-Signature Preflight Slice

## Scope

- Bead: `plib-jbwl`
- Area: shared ZIP/OPC package ingestion core blocker
- Date: 2026-06-10 UTC

## Change

`ZipPackage::splitArchivePreflight()` now scans the declared central-directory entry count first, then records non-entry central-directory records separately. Central-directory digital signatures inside the declared central-directory byte range are surfaced as `centralDirectoryNonEntryRecords` instead of being treated as invalid split-archive structure.

This keeps split-disk policy focused on EOCD disk markers and per-entry `diskStart` metadata while preserving signed central-directory provenance for DOCX/EPUB/ODF package review.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` - 1 file, 2976 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 44 files, 60955 assertions, 0 failures

No Pandoc, Cabal/Haskell runner, office suite, zip/unzip, browser renderer, external validator, online service, live provider test, or live-service provider test was executed.
