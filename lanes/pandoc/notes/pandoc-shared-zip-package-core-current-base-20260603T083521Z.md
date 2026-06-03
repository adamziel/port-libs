# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260603T083521Z`

Base accepted HEAD: `aa85594b5b6477c7adeb3ac23de2b4289053af14`

## Behavior Added

- Extended `ZipPackage` with native deterministic ZIP package assembly for
  generated Pandoc containers.
- `ZipPackage::fromParts()` and `ZipPackage::build()` now emit single-disk ZIP
  bytes with stored or deflated parts, UTF-8 central-directory metadata,
  package comments, entry comments, CRCs, local headers, central directory
  records, and EOCD records.
- Generated package bytes round-trip through the existing central-directory
  reader before consumers use them.
- Writer guards reject duplicate names, unsafe package paths, unsupported
  compression methods, directory entries with file data, non-string data or
  comments, overlong comments/names, and ZIP64-sized offsets or payloads.
- The WordPress ZIP preflight example now uses the production `ZipPackage`
  writer instead of carrying a test-local ZIP byte builder.

## Source Truth

- This continues the accepted `shared-zip-package-core` support row for
  DOCX/EPUB/ODT-style package containers.
- The local Pandoc upstream checkout is not present in the current cache, so no
  Haskell runner or upstream binary was executed. The behavior is bounded to
  standard ZIP local-header, central-directory, and EOCD package semantics
  already used by the lane's accepted reader and OPC package primitives.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 59 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `4 test files, 2474 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

This reuses and extends the existing native PHP `shared-zip-package-core`
component. It requires PHP zlib for raw DEFLATE output and does not require
`ZipArchive`, external `zip`/`unzip`, Pandoc, Word, LibreOffice, TeX/PDF
engines, external template engines, Haskell test binaries, or online services.

## Non-Overlap

This does not edit dashboard/progress files and does not implement DOCX body XML
conversion, OPC content-type or relationship XML semantics, doctemplate
rendering, YAML metadata, CSL/citations, EPUB/ODT-specific manifests, PDF
handoff, or upstream runner dependency closure.

## Follow-Up

Next package-layer work should combine `ZipPackage` read/write support with the
accepted OPC helpers to parse a minimal DOCX document part into the existing
Pandoc AST and WordPress handoff path.
