# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T043434Z`

Base accepted HEAD: `e50f09220eaa5f3cade103838843b7f3c365e963`

## Implementation

- Added bounded ZIP member reads to `ZipPackage`:
  - `read($partName, $maxUncompressedBytes)` keeps existing unbounded behavior
    when the limit is omitted;
  - `readBounded($partName, $maxUncompressedBytes)` gives DOCX/EPUB/ODT
    preflight callers an explicit bounded-read API;
  - the limit is checked against central-directory uncompressed size before
    reading compressed bytes, and checked again after inflate for malformed
    size metadata.
- Updated the WordPress ZIP package preflight smoke so ordinary document XML
  can be read under a generous cap while oversized embedded media is rejected
  before bytes are exposed to import code.

## Source Truth

This stays inside the accepted `pandoc-shared-zip-package-core-*` support row
for DOCX/EPUB/ODT-style ZIP containers. Pandoc package conversion needs native
PHP archive member reads that can be bounded by callers before embedded Office
media or review packet resources are handed to higher-level readers. This is
not full ZIP bomb detection or ZIP64 support; it is a narrow per-entry
uncompressed-size guard over the existing central-directory metadata and
DEFLATE path.

No Pandoc, Cabal build, Haskell runner, ZipArchive, Word, LibreOffice, `zip`,
`unzip`, TeX/PDF engine, external template engine, browser renderer, or online
service was executed.

## Evidence

- Baseline focused command before edits:
  - `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 232 assertions, 0 failures`.
- After implementation:
  - `php -l lanes/pandoc/src/ZipPackage.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 238 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Delta

- Focused PHP PASS cases: `623 -> 624` lane total.
- Focused ZIP assertions: `232 -> 238` for `ZipPackageTest.php`, adding 6
  assertions.
- Manifest mapped checks: `1097 -> 1098`.
- ZIP package support cases: `21 -> 22`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`ZipPackage` primitive and reuses PHP string bounds, in-process CRC, and raw
DEFLATE handling. Full upstream Pandoc runner parity remains blocked on
hydrating/building the Haskell Pandoc checkout at the manifest commit, but
bounded ZIP member reads are not blocked by that runner.

## Non-Overlap

This does not repeat accepted ZIP central-directory parsing, package writing,
central/local extra-field parsing, extended or NTFS timestamp handling,
local-header CRC/size checks, data-descriptor validation, ZIP64 extra-field
rejection, Unix symlink rejection, drive-letter path rejection, directory
payload rejection, gzip/tar/LZ4 archive streams, OPC relationships/content
types, DOCX/ODT/EPUB readers, syntax highlighting, table geometry, or
Markdown/HTML reader and writer behavior. It only adds bounded per-entry
uncompressed ZIP reads and the WordPress preflight smoke for that policy.

## Follow-Up

Keep default per-reader size-limit policy, compression-ratio diagnostics,
full ZIP64 large archive support, AES/encrypted archives, central-directory
digital-signature handling, multi-disk ZIPs, and extra compression methods as
separate bounded slices.
