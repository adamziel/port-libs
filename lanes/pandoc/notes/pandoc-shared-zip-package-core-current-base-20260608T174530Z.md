# pandoc-shared-zip-package-core-current-base-20260608T174530Z

Accepted base: `9965dd418ac9194ca9784a6dc4cecce9c13d164f`

## Behavior

Added native ZIP raw local-header span preflight in `ZipPackage::localHeaderSpanPreflight()` and wired it into `ZipPackage::rawStrictImportPreflight()`.

The new preflight scans EOCD and central-directory records without instantiating the package, then reports local-header layout spans for strict DOCX/ODT/EPUB package import review:

- unclaimed bytes between a central-directory entry's local record and the next local header or central directory
- whether unclaimed bytes start with an orphan local-header signature
- duplicate local-header offsets
- local header or compressed payload overlap with the next entry or central directory
- data-descriptor span issues when descriptor-backed entries cannot be safely scanned

This is bounded support-library behavior only. It does not shell out to Pandoc, Word, LibreOffice, `zip`, `unzip`, `ZipArchive`, Cabal/Haskell runners, or online services.

## Evidence

Baseline before the new test:

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- `1 test files, 1599 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
- `zip package writer preflight self-test passed`

Focused verification after the patch:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `No syntax errors detected in lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `No syntax errors detected in lanes/pandoc/tests/ZipPackageTest.php`
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
- `No syntax errors detected in lanes/pandoc/examples/wordpress-zip-package-preflight.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- `1 test files, 1624 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
- `zip package writer preflight self-test passed`

Focused assertion delta: `+25`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. This reuses the existing `ZipPackage` EOCD, central-directory, local-header metadata, data-descriptor, and WordPress ZIP preflight support.

Remaining exclusions: full ZIP64 EOCD parsing, encrypted package extraction, cryptographic signature verification, non-deflate codec expansion, full upstream Pandoc runner parity, Word/LibreOffice behavior parity, `zip`/`unzip`/`ZipArchive`, Cabal/Haskell runners, online services, live provider tests, and live-service provider tests.

## Non-Overlap

This slice does not repeat the accepted ZIP central-directory digital signature provenance, trailing-deflate payload-integrity rejection, Unicode-name collision preflight, invalid DOS timestamp preflight, or ZIP data-descriptor package-stream provenance slices. It adds raw package layout evidence for orphan/unclaimed local-header bytes before package instantiation.

