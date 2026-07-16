# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T064048Z`

Base accepted HEAD: `241ad53c7818cc3936c2f814f52cd1f3a78b9d42`

## Implementation

- Added bounded central-directory tail preflight to `ZipPackage`.
- `ZipPackage::fromString()` now requires the parsed central-directory records
  to end exactly at the EOCD record, and rejects `PK\x05\x05`
  central-directory digital-signature records instead of silently ignoring
  them.
- Updated the WordPress ZIP package preflight smoke so signed central-directory
  tails are classified as rejected before embedded Office package bytes are
  importable.

## Source Truth

Pandoc DOCX, EPUB, and ODT readers depend on ZIP central-directory records as
the authoritative package part inventory. A central-directory digital-signature
tail is not parsed or verified by this bounded native PHP reader, so accepting
it would expose package parts while silently ignoring central-directory bytes.
The safe local contract is to reject that archive shape until a dedicated
signature verification/stripping policy exists.

No Pandoc, Cabal build, Haskell runner, `ZipArchive`, Word, LibreOffice,
`zip`, `unzip`, tar CLI, LZ4 CLI, TeX/PDF engine, external template engine,
browser renderer, online sanitizer, or online service was executed.

## Red-First Evidence

- Baseline `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 251 assertions, 0 failures`.
- Red check after adding expectations:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 252 assertions, 1 failures`.
  - Failure: `Expected exception RuntimeException was not thrown` for a ZIP
    package carrying a central-directory digital-signature tail before EOCD.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`
  - Result: both JSON files decoded successfully.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 252 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Delta

- Focused ZIP assertions: `251 -> 252`, adding 1 assertion.
- Manifest mapped checks: `1177 -> 1178`.
- ZIP package support cases: `21 -> 22`.
- ZIP package core assertions: `131 -> 132`.
- Lane PHP pass count: `717 -> 718`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage` and `ZipPackageEntry` primitives and stays within the accepted
ZIP/OPC package support row. Full upstream Pandoc runner parity remains
blocked on hydrating/building the Haskell Pandoc checkout at the manifest
commit, but central-directory tail preflight is not blocked by that runner.

## Non-Overlap

This does not repeat accepted central-directory parsing, package writing, local
entry order exposure, data descriptors, CRC/local-header checks,
central/local extra-field parsing, extended or NTFS timestamp handling, ZIP64
extra-field rejection, Unix symlink rejection, raw/decoded unsafe path
rejection, directory payload rejection, local-entry overlap rejection,
duplicate local-header-offset rejection, bounded reads, gzip/tar/LZ4 archive
streams, OPC relationships/content types, DOCX/ODT/EPUB readers, syntax
highlighting, table geometry, math/TeX, doctemplates, or Markdown/HTML reader
and writer behavior. It only rejects unparsed central-directory tail bytes,
including digital-signature records, before package parts are exposed.

## Follow-Up

Keep compression-ratio diagnostics, default per-reader size-limit policy,
extra compression methods, AES/encrypted archives, spanning archives, full
ZIP64 large-archive support, and any future verified central-directory
signature policy as separate bounded ZIP package slices.
