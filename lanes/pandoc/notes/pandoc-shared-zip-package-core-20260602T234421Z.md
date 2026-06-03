# Pandoc shared ZIP package core 2026-06-02T23:44:21Z

## Slice

- Added `ZipPackage` and `ZipPackageEntry` as bounded native PHP package primitives for DOCX/EPUB/ODT-style ZIP containers.
- The reader parses the end-of-central-directory record and central directory from bytes, rejects split, ZIP64, encrypted, duplicate, and unsafe package entries, and verifies local-header consistency before returning part bytes.
- Stored and deflated entries are supported, including entries whose local header uses the data-descriptor flag while authoritative sizes and CRC are read from the central directory.
- Added a WordPress import preflight example that opens a DOCX-like package and verifies `/word/document.xml` without shelling out to Pandoc, Word, LibreOffice, `zip`, or `unzip`.

## Evidence

- `php -l lanes/pandoc/src/ZipPackage.php` passed.
- `php -l lanes/pandoc/src/ZipPackageEntry.php` passed.
- `php -l lanes/pandoc/tests/ZipPackageTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed: 1 test file, 32 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 2 test files, 2,349 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test` passed.
- Root harness not run - isolated micro-slice.

## Dependency Closure

This activates the smallest native PHP support component needed for package-backed Pandoc formats: single-disk ZIP package reads with stored and deflated entries. It reuses PHP zlib for raw DEFLATE inflation and does not require `ZipArchive`, external `zip`/`unzip`, Pandoc, Word, LibreOffice, TeX/PDF engines, template engines, or online services.

## Follow-Up

Next package-layer work should build on `ZipPackage` with OPC content-types and relationships XML parsing for DOCX/EPUB/ODT part discovery. ZIP64, split archives, encryption, and unsupported compression methods remain intentionally guarded out of scope until a concrete fixture requires them.
