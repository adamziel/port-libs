# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T074526Z`

Base accepted HEAD: `2f304e09099aaf78501d0db5c12b98a416d2fac1`

## Implementation

- Added ZIP version-needed-to-extract metadata to `ZipPackageEntry`.
- `ZipPackage::fromString()` now preserves the central-directory version-needed
  field for each entry and rejects local headers whose version-needed field
  disagrees with the central-directory record.
- Updated the WordPress ZIP package preflight smoke so review packets expose
  the version-needed value and reject local/central mismatches before Office or
  EPUB package parts are importable.

## Source Truth

Pandoc DOCX, EPUB, and ODT readers depend on ZIP package metadata before
reader-specific XML/media parsing. The central directory is the authoritative
bounded package inventory in this native support layer, but the local header is
the byte-level source consumed before reading entry data. A local/central
version-needed mismatch makes package capability metadata ambiguous, so this
slice rejects that shape during package preflight and exposes the agreed value
for WordPress import review.

No Pandoc, Cabal build, Haskell runner, `ZipArchive`, Word, LibreOffice,
`zip`, `unzip`, tar CLI, LZ4 CLI, TeX/PDF engine, external template engine,
browser renderer, online sanitizer, or online service was executed.

## Verification

- Baseline `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 274 assertions, 0 failures`.
- `php -l lanes/pandoc/src/ZipPackage.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/ZipPackageEntry.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`
  - Result: both JSON files decoded successfully.
- Focused `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 281 assertions, 0 failures`.
- Lane-scoped `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 8810 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Delta

- Focused ZIP assertions: `274 -> 281`, adding 7 assertions.
- Focused ZIP PASS cases: `40 -> 41`, adding 1 PASS case.
- Manifest mapped checks: `1212 -> 1213`.
- ZIP package support cases: `21 -> 22`.
- ZIP package core assertions: `131 -> 138`.
- Lane PHP pass count: `753 -> 754`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage` and `ZipPackageEntry` primitives and stays within the accepted
ZIP/OPC package support row. Full upstream Pandoc runner parity remains
blocked on hydrating/building the Haskell Pandoc checkout at the manifest
commit, but ZIP version-needed metadata preflight is not blocked by that
runner.

## Non-Overlap

This does not repeat accepted central-directory parsing, package writing,
local entry order exposure, data descriptors, CRC/local-header name checks,
central/local extra-field parsing, extended or NTFS timestamp handling,
ZIP64 extra-field rejection, Unix symlink rejection, raw/decoded unsafe path
rejection, directory payload rejection, local-entry overlap rejection,
duplicate local-header-offset rejection, central-directory tail rejection,
aggregate size preflight, bounded per-entry reads, gzip/tar/LZ4 archive
streams, OPC relationships/content types, DOCX/ODT/EPUB readers, syntax
highlighting, table geometry, math/TeX, doctemplates, or Markdown/HTML reader
and writer behavior. It only exposes and cross-checks ZIP version-needed
metadata for package preflight.

## Follow-Up

Keep AES/encrypted archive payload support, spanning archives, verified
central-directory signatures, full ZIP64 large-archive support, reader-specific
default size policies, and additional compression methods as separate bounded
ZIP package slices.
