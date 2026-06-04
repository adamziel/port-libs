# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260604T163112Z`

Base accepted HEAD: `936e01b75da46937a815da1572560a12a8f9c02b`

## Implementation

- Added bounded ZIP NTFS extra-field parsing for Office-style package
  metadata:
  - `ZipPackageEntry::ntfsTimestamps()` exposes central-directory NTFS
    modified/accessed/created FILETIME values as Unix timestamps;
  - `ZipPackageEntry::ntfsLastModifiedTimestamp()` feeds
    `lastModifiedTimestamp()` before DOS timestamp fallback when no Info-ZIP
    extended timestamp is present;
  - `ZipPackage::localNtfsTimestamps()` and
    `localNtfsLastModifiedTimestamp()` expose local-header NTFS metadata for
    import preflight.
- Added strict malformed NTFS extra-field guards for truncated reserved bytes,
  truncated tagged attributes, and timestamp attributes that do not contain
  the three required FILETIME values.
- Added a local-vs-central NTFS timestamp mismatch guard before package parts
  are returned to DOCX/EPUB/ODT readers.
- Updated the WordPress ZIP package preflight smoke to report NTFS media
  timestamps for Office attachment import audits.

## Source Truth

This stays inside the accepted `pandoc-shared-zip-package-core-*` support row
for DOCX/EPUB/ODT-style ZIP containers. Ordinary ZIP packages may carry NTFS
extra field `0x000a`, whose payload starts with four reserved bytes and then
tagged attributes; tag `0x0001` carries modified, accessed, and created
Windows FILETIME values. WordPress import preflight needs this package metadata
before treating embedded Office media bytes as attachment candidates.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `zip`/`unzip`, TeX
or PDF engine, external template engine, browser renderer, or online service
was executed.

## Red/Green Evidence

- Red-first command:
  - `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result before implementation: `1 test files, 149 assertions, 2 failures`.
  - Failures: the new NTFS timestamp tests failed because
    `ZipPackageEntry::ntfsTimestamps()` and
    `ZipPackage::localNtfsTimestamps()` did not exist yet.
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
  - Result: `1 test files, 158 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `11 test files, 3270 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - `git diff --check -- lanes/pandoc`
  - Result: passed.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`ZipPackage` and `ZipPackageEntry` primitives and reuses the accepted bounded
extra-field parser. It does not require `ZipArchive`, external `zip`/`unzip`,
Pandoc, office tools, TeX/PDF engines, external template engines, Haskell test
binaries, or online services.

## Non-Overlap

This does not repeat accepted ZIP central-directory parsing, package writing,
central/local generic extra-field parsing, Info-ZIP extended timestamp parsing
or writing, local-header CRC/size checks, data-descriptor handling, gzip stream
framing, OPC relationships and content types, DOCX/ODT package readers,
doctemplates, YAML metadata, CSL/citation handling, table geometry, math/TeX
conversion, PDF engine handoff planning, legacy DOC/CFB extraction, or
Markdown/HTML reader and writer behavior. It only adds bounded NTFS extra-field
timestamp parsing and mismatch preflight in the ZIP package layer.

## Follow-Up

Keep ZIP64, symlink external-attribute policy, encrypted ZIP entries, tar file
entries, LZ4 frames, and higher-level package diagnostics as separate bounded
slices unless concrete Pandoc fixtures require them.
