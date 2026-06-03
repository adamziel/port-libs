# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260603T233943Z`

Base accepted HEAD: `9f8ae4fb71ff5c28527f923a11d9eebb6d57eab4`

## Implementation

- Added bounded ZIP extra-field support to `ZipPackageEntry`:
  - raw central-directory extra-field bytes are preserved;
  - central extra fields are parsed into id/data records for package preflight;
  - Info-ZIP extended timestamp field `0x5455` is surfaced as an exact
    modified timestamp and preferred over DOS two-second timestamps.
- `ZipPackage` now carries central extra fields from the central directory,
  validates local-header extra fields before returning part bytes, and writes
  `0x5455` extended timestamp metadata when generated package parts use
  `modifiedAt`.
- The WordPress ZIP package preflight smoke now verifies exact modified-time
  round-trip via the extended timestamp extra field and reports extra-field
  counts for package entries.

## Source Truth

This remains inside the accepted `shared-zip-package-core` support row for
DOCX/EPUB/ODT-style ZIP containers. ZIP extra fields are part of ordinary ZIP
local-header and central-directory package semantics; `0x5455` extended
timestamps are a common bounded metadata handoff needed to preserve exact
source mtimes without relying on `ZipArchive`, external `zip`/`unzip`, or
office tooling.

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
  - Result: `1 test files, 106 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `7 test files, 2838 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
- `git diff --check -- lanes/pandoc`
  - Result: passed.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`ZipPackage` reader/writer and reuses PHP zlib raw DEFLATE support already
used by the accepted ZIP package core. It does not use `ZipArchive`, external
`zip`/`unzip`, Pandoc, Word, LibreOffice, TeX/PDF engines, external template
engines, Haskell test binaries, or online services.

## Non-Overlap

This does not repeat accepted ZIP central-directory parsing, package writing,
CRC/size/timestamp local-header validation, data-descriptor validation, OPC
content-types/relationships XML, OPC relationship graph preflight, DOCX body
parsing, doctemplates, YAML metadata, CSL/citations, math/TeX conversion, or
Markdown/HTML reader and writer behavior. It only extends the shared package
primitive with central/local extra-field handling and exact extended timestamp
metadata.

## Follow-Up

Keep ZIP64, symlink external attributes, NTFS timestamp extra fields, and
higher-level DOCX/EPUB/ODT diagnostics as separate gates unless a concrete
fixture requires them.
