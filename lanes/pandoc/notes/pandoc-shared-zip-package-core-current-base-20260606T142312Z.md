# pandoc-shared-zip-package-core-current-base-20260606T142312Z

Lane: pandoc
Base accepted HEAD: 864fa416793ca581880ed9a5ef60aae3d908fe99
Scope: ZIP/OPC package primitives under `lanes/pandoc/**`

## Behavior

`ZipPackage` now rejects Unix external file-type metadata that disagrees with the ZIP entry name shape before DOCX/EPUB/ODT media import or generated package output:

- directory-shaped entries such as `word/media/` must not carry Unix regular-file type bits;
- file-shaped entries such as `word/media/reviewer-folder` must not carry Unix directory type bits;
- non-Unix creator-host metadata remains metadata-only for parsed packages.

This closes a package preflight gap adjacent to the already accepted directory payload, DOS directory attribute, Unix symlink, Unix special-file, and ZIP local-header span checks. The change does not shell out to Pandoc, zip/unzip, ZipArchive, Word, LibreOffice, or external archive tools.

## Evidence

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- Result: `1 test files, 819 assertions, 0 failures`

Focused checks after this slice:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`

Results:

- All changed PHP files reported no syntax errors.
- `ZipPackageTest.php` passed with `1 test files, 830 assertions, 0 failures`.
- `wordpress-zip-package-preflight.php --self-test` printed `zip package writer preflight self-test passed`.

## Status Delta

- Mapped native support cases: `+1`
- Focused PHP PASS cases: `+1`
- Focused assertions: `+11` (`819 -> 830`)
- `lane-status.json` `phpPass`: `1345 -> 1346`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1759 -> 1760`
- `mappedZipPackageCoreSupportCases`: `22 -> 23`

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP `ZipPackage` / `ZipPackageEntry` ZIP external-attribute metadata support.

Out of scope for this slice: ZIP64 large archive payloads, encrypted or AES payloads, cryptographic verification of central-directory signatures, split archives, unsupported compression methods beyond store/deflate, filesystem-specific metadata beyond bounded Unix/DOS checks, and external Pandoc/archive-tool runner parity.

## Non-Overlap

This slice does not repeat the accepted ZIP central-directory signature, trailing-deflate integrity, Unicode-normalized case-insensitive name collision, local header span, symlink, special-file, DOS directory attribute, or directory payload checks. It only adds Unix external file-type/name-shape consistency for parsed and generated ZIP packages.
