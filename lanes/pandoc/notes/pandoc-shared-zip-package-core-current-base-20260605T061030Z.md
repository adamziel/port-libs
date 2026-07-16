# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T061030Z`

Base accepted HEAD: `71490a93df4cf6044eeb41e4b9e398006aa2b59b`

## Implementation

- Added bounded duplicate local-header-offset preflight to `ZipPackage`.
- `ZipPackage::fromString()` now rejects central-directory records that point
  at a local file header offset already claimed by another entry.
- Updated the WordPress ZIP package preflight smoke so review packets report
  duplicate local headers as rejected before embedded Office media bytes are
  importable.

## Source Truth

Pandoc DOCX, EPUB, and ODT readers depend on ZIP central-directory records as
package part inventory. A malformed archive that maps more than one central
record to the same local file header can expose ambiguous names or media
metadata before the actual local header is read. This slice keeps the behavior
bounded to native PHP package preflight and rejects the ambiguous container
shape during `ZipPackage::fromString()`.

No Pandoc, Cabal build, Haskell runner, `ZipArchive`, Word, LibreOffice,
`zip`, `unzip`, tar CLI, LZ4 CLI, TeX/PDF engine, external template engine,
browser renderer, online sanitizer, or online service was executed.

## Red-First Evidence

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result before implementation: 1 failure.
  - Failure: `Expected exception RuntimeException was not thrown` for
    duplicate ZIP local header offsets.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 251 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 7942 assertions, 0 failures`.
  - Counted PASS lines: `681`.

Root harness not run - isolated micro-slice.

## Delta

- Focused ZIP assertions: `250 -> 251`, adding 1 assertion.
- Manifest mapped checks: `1157 -> 1158`.
- ZIP package support cases: `21 -> 22`.
- ZIP package core assertions: `131 -> 132`.
- Full lane PASS lines verified at `681`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage` and `ZipPackageEntry` primitives and stays within the accepted
ZIP/OPC package support row. Full upstream Pandoc runner parity remains
blocked on hydrating/building the Haskell Pandoc checkout at the manifest
commit, but duplicate local-header-offset preflight is not blocked by that
runner.

## Non-Overlap

This does not repeat accepted central-directory parsing, package writing, local
entry order exposure, data descriptors, CRC/local-header checks, central/local
extra-field parsing, extended or NTFS timestamp handling, ZIP64 extra-field
rejection, Unix symlink rejection, raw/decoded unsafe path rejection, directory
payload rejection, bounded reads, gzip/tar/LZ4 archive streams, OPC
relationships/content types, DOCX/ODT/EPUB readers, syntax highlighting, table
geometry, math/TeX, doctemplates, or Markdown/HTML reader and writer behavior.
It only rejects central-directory records that reuse an already claimed local
file header offset.

## Follow-Up

Keep central-directory digital signature records, compression-ratio diagnostics,
default per-reader size-limit policy, extra compression methods, AES/encrypted
archives, spanning archives, and full ZIP64 large-archive support as separate
bounded ZIP package slices.
