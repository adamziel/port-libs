# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260603T085715Z`

## Implementation

- Strengthened `ZipPackage` local-file integrity checks for package-backed
  Pandoc formats:
  - ordinary entries now reject local-header CRC or size values that disagree
    with the central directory;
  - data-descriptor entries now validate the trailing 32-bit descriptor CRC,
    compressed size, and uncompressed size before returning part bytes;
  - both descriptor shapes are accepted: with the optional `PK\x07\x08`
    signature and without it.
- Extended the ZIP package test fixture builder so focused tests can generate
  signed descriptors, unsigned descriptors, and corrupted local/descriptor
  metadata without shelling out to `zip` or `unzip`.
- Updated `wordpress-zip-package-preflight.php` to keep the accepted generated
  package writer smoke and add a descriptor-backed `word/comments.xml` reader
  smoke, giving the WordPress import preflight a reviewer comments path in
  addition to the main document part.

## Evidence

- `php -l lanes/pandoc/src/ZipPackage.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: 1 test file, 40 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 5 test files, 2641 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package preflight self-test passed`.
- `git diff --check -- lanes/pandoc`
  - Result: passed.

Root harness not run - isolated micro-slice.

## Dependency Closure

No external support component is needed. This slice reuses the accepted native
PHP ZIP package reader and PHP zlib raw DEFLATE handling, and adds the bounded
ZIP integrity checks required before OPC/DOCX/EPUB/ODT package parts are handed
to XML readers. It does not use `ZipArchive`, Pandoc, Word, LibreOffice,
`zip`/`unzip`, TeX/PDF engines, template engines, online services, ZIP64,
split archives, encrypted archives, or unsupported compression methods.

## Non-Overlap

This does not repeat the accepted OPC content-types/relationships XML slice,
doctemplate rendering, YAML metadata parsing, Markdown/HTML reader branches, or
Markdown writer behavior. It only extends the shared ZIP package primitive with
local-header and data-descriptor validation needed by richer document-container
conversion.

## Next

Use the strengthened ZIP/OPC primitives to parse a minimal DOCX document part
plus metadata into the existing Pandoc AST/WordPress handoff path.
