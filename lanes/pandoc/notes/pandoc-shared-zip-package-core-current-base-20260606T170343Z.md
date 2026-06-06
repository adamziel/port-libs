# pandoc-shared-zip-package-core-current-base-20260606T170343Z

Lane: pandoc
Base accepted HEAD: 2fafdab3d147dccac973662b1b9ba5c7bdadcbfd
Scope: ZIP/OPC package primitives under `lanes/pandoc/**`

## Behavior

`ZipPackage` now rejects duplicate Info-ZIP Unicode path (`0x7075`) and Unicode comment (`0x6375`) extra fields before DOCX, EPUB, ODT, or WordPress package handoffs consume entry names, entry comments, or media bytes.

This covers:

- duplicate Unicode path extra fields in the central directory entry;
- duplicate Unicode path extra fields in the local header entry;
- duplicate Unicode comment extra fields in the central directory entry;
- the existing safe single Unicode path/comment field decode path remains readable.

The WordPress ZIP package preflight smoke now self-tests this rejection and reports `zipDuplicateUnicodeExtraPolicy`.

No Pandoc, Cabal, Haskell runner, Word, LibreOffice, zip/unzip, ZipArchive, external archive tool, online service, live provider test, or live-service provider test was executed.

## Source Truth

Pandoc's DOCX, ODT, and EPUB readers consume ZIP/OPC-backed packages where archive entry names identify package parts and media. The Info-ZIP Unicode Path and Unicode Comment extra fields are authoritative metadata for legacy non-UTF-8 names and comments. This slice keeps that bounded support-library contract deterministic by rejecting multiple authoritative Unicode fields for the same name/comment slot instead of choosing one silently.

## Evidence

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- Result: `1 test files, 862 assertions, 0 failures`

Red-first focused check:

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- Result: `1 test files, 863 assertions, 1 failures`
- Failing assertion: duplicate Info-ZIP Unicode path/comment extra fields were still accepted before implementation.

Focused checks after this slice:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`

Results:

- All changed PHP files reported no syntax errors.
- `ZipPackageTest.php` passed with `1 test files, 873 assertions, 0 failures`.
- `wordpress-zip-package-preflight.php --self-test` printed `zip package writer preflight self-test passed`.

## Status Delta

- Mapped native support cases: `+1`
- Focused PHP PASS cases: `+1`
- Focused assertions: `+11` (`862 -> 873`)
- `lane-status.json` `phpPass`: `1369 -> 1370`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1782 -> 1783`
- `mappedZipPackageCoreSupportCases`: `22 -> 23`
- `zipPackageCoreAssertions`: `161 -> 172`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`, `ZipPackageEntry` extra-field parsing, CRC32 validation, UTF-8 validation, in-memory ZIP fixtures, and the existing WordPress ZIP package preflight example.

Out of scope for this slice: ZIP64 large payloads, encrypted/AES payloads, split archives, unsupported compression methods beyond store/deflate, reader-level strict-preflight adoption, and full upstream Pandoc runner parity.

## Non-Overlap

This slice does not repeat the accepted ZIP central-directory signature, trailing-deflate integrity, Unicode-normalized case-insensitive name collision, local header span, symlink, special-file, DOS directory attribute, directory payload, Unix external file-type/name-shape, raw UTF-8 flag, mismatched Unicode extra-field CRC, or unsupported generic extra-field checks. It only adds duplicate Info-ZIP Unicode path/comment extra-field rejection.
