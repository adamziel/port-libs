# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260603T093016Z`

Base accepted HEAD: `ccdbc8f5f239ec3e14bb71edbef4e8cc79cd8677`

## Implementation

- Extended `ZipPackageEntry` with central-directory modification time/date,
  external file attributes, raw DOS timestamp accessors, and a UTC timestamp
  helper.
- `ZipPackage` now reads those central-directory fields, validates that local
  headers carry the same modification timestamp, and preserves the metadata
  while returning stored or deflated part bytes.
- `ZipPackage::fromParts()` / `ZipPackage::build()` can emit generated package
  parts with either a deterministic `modifiedAt` Unix timestamp or raw DOS
  timestamp fields plus explicit external attributes. Directory entries receive
  the DOS directory external attribute by default.
- `wordpress-zip-package-preflight.php` now verifies and reports document-part
  ZIP timestamp and external-attribute metadata alongside the accepted
  descriptor-backed comments-part smoke.

## Source Truth

This keeps the slice inside the accepted `shared-zip-package-core` support row
for DOCX/EPUB/ODT-style ZIP containers. ZIP modification timestamps and
external attributes are standard central-directory fields needed by package
preflight and import diagnostics. The behavior remains bounded to native PHP
single-disk ZIP package semantics already used by the lane's OPC package
primitives.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/ZipPackageEntry.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 89 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `5 test files, 2663 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
- `git diff --check -- lanes/pandoc`
  - Result: passed.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`ZipPackage` reader/writer and PHP zlib raw DEFLATE support. It does not use
`ZipArchive`, external `zip`/`unzip`, Pandoc, Word, LibreOffice, TeX/PDF
engines, external template engines, Haskell test binaries, or online services.

## Non-Overlap

This does not repeat OPC content-types/relationships XML parsing, ZIP-backed
relationship graph discovery, doctemplate rendering, YAML metadata parsing,
CSL/citation processing, Markdown/HTML reader behavior, or Markdown/WordPress
writer behavior. It only extends shared ZIP package primitives with entry
metadata needed by richer document-container conversion.

## Follow-Up

Use the strengthened ZIP/OPC primitives to parse a minimal DOCX document part
plus package/core metadata into the existing Pandoc AST and WordPress handoff
path.
