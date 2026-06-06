# Pandoc Shared ZIP Package Core Current Base - 2026-06-06T064553Z

## Scope

Implemented a bounded native PHP ZIP package preflight for case-insensitive entry-name collisions. Exact ZIP names remain addressable by exact case, but strict import now reports and rejects archives where entries such as `word/media/Review.PNG` and `word/media/review.png` would collide on case-insensitive extraction targets.

This is a shared ZIP primitive for DOCX/EPUB/Office media handoff. It does not shell out to Pandoc, Word, LibreOffice, `zip`, `unzip`, `ZipArchive`, or online services.

## Implementation

- Added `ZipPackage::caseInsensitiveNamePreflight()` with collision groups, per-entry case-fold keys, equivalent entry names, and issue codes.
- Added `ZipPackage::assertNoCaseInsensitiveNameCollisions()`.
- Added `case-insensitive-name-collisions` to `ZipPackage::strictImportPreflight()`.
- Updated the WordPress ZIP package preflight example to cover clean-package zero collisions and a rejected media-name collision.

## Verification

Baseline before changes:

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- Result: `1 test files, 719 assertions, 0 failures`.

After implementation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- Result: no syntax errors.
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
- Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- Result: `1 test files, 749 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
- Result: `zip package writer preflight self-test passed`.

Focused delta: +1 PHP PASS case and +30 focused assertions.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `ZipPackage`/`ZipPackageEntry` parser and strict preflight model. Full upstream Pandoc runner parity remains blocked on a hydrated Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` plus Cabal runner package/project files; no Haskell runner or external converter was executed.

## Non-Overlap

This does not repeat existing exact duplicate entry-name rejection, file/directory path hierarchy collision preflight, local-header overlap/slack rejection, ZIP64/data-descriptor handling, comment/extra-field policy, permission policy, unsupported compression policy, or OPC relationship part-name equivalence preflight. This slice owns only shared ZIP entry-name ambiguity before media extraction.

## Follow-Up

Keep encrypted central-directory handling, full ZIP64 read support, archive extra data record policy, broader compression methods, Unicode case folding beyond ASCII, and actual DOCX/EPUB media extraction policy as separate bounded slices.
