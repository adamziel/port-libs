# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260604T104436Z`

Base accepted HEAD: `ceef47806c3d0e479408d9ba3cd04205f40c9bee`

## Implementation

- Added bounded local-header ZIP extra-field inspection to `ZipPackage`:
  - `localExtraFields()` parses local extra-field id/data records;
  - `localExtraField()` exposes a single local extra-field payload by id;
  - `localExtendedLastModifiedTimestamp()` surfaces local `0x5455` extended
    timestamp metadata for package preflight.
- Refactored local-file-header validation so local extra-field preflight and
  part-byte reads share name, flag, method, DOS timestamp, CRC, size, and
  malformed extra-field checks.
- Added a metadata integrity guard that rejects entries whose local-header
  `0x5455` modified timestamp conflicts with the central-directory `0x5455`
  modified timestamp before DOCX/EPUB/ODT readers receive part bytes.
- Updated the WordPress ZIP package preflight smoke to verify local extra-field
  inspection and report local extra-field counts.

## Source Truth

This stays inside the accepted `pandoc-shared-zip-package-core-*` support row
for DOCX/EPUB/ODT-style ZIP containers. ZIP local headers and central
directory entries can both carry extra fields, and package import preflight
needs access to the local-header metadata before handing a part to higher-level
readers. The implementation keeps the scope bounded to single-disk,
non-encrypted, non-ZIP64 package primitives and does not shell out.

## Red/Green Evidence

- Red-first command:
  - `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result before implementation: `1 test files, 143 assertions, 2 failures`.
  - Failures: the two new local extra-field cases failed because
    `ZipPackage::localExtraField()` / `ZipPackage::localExtraFields()` were
    not implemented.
- After implementation:
  - `php -l lanes/pandoc/src/ZipPackage.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/src/ZipPackageEntry.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 148 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `9 test files, 3022 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - `git diff --check -- lanes/pandoc`
  - Result: passed.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`ZipPackage` and `ZipPackageEntry` primitives and reuses the accepted bounded
extra-field parser and PHP zlib raw DEFLATE support. It does not use
`ZipArchive`, external `zip`/`unzip`, Pandoc, Word, LibreOffice, TeX/PDF
engines, external template engines, Haskell test binaries, or online services.

## Non-Overlap

This does not repeat accepted ZIP central-directory parsing, package writing,
central extra-field parsing, extended timestamp writing, local-header CRC/size
checks, data-descriptor handling, gzip stream framing, OPC relationships and
content types, DOCX/ODT package readers, doctemplates, YAML metadata,
CSL/citation handling, table geometry, math/TeX conversion, PDF engine
handoff planning, or Markdown/HTML reader and writer behavior. It only adds
local-header extra-field inspection and local-vs-central extended timestamp
preflight.

## Follow-Up

Keep ZIP64, symlink external-attribute policy, NTFS timestamp extra fields,
encrypted ZIP entries, tar file entries, LZ4 frames, and higher-level package
diagnostics as separate bounded slices unless concrete Pandoc fixtures require
them.
