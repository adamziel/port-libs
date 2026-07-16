# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T013446Z`

Base accepted HEAD: `6db7c58238c121b42ac6e51a845daff027aacdea`

## Implementation

- Added bounded writer-side ZIP extra-field support for generated
  DOCX/EPUB/ODT-style package parts.
- `ZipPackage::fromParts()` / `ZipPackage::build()` now accept
  `extraFieldData` bytes per entry, validate them with the existing
  `ZipPackageEntry` extra-field parser, and emit them to both local and
  central headers.
- Generated `modifiedAt` metadata still emits the existing `0x5455` extended
  timestamp field first; custom extra fields are appended after it.
- Malformed writer extra fields, non-string `extraFieldData`, and ZIP64
  Extended Information extra fields remain rejected before package bytes are
  exposed.
- Updated the WordPress ZIP package preflight example to prove a generated
  `0xcafe` review provenance field round-trips from central and local headers.

## Source Truth

This stays inside the shared ZIP package support row needed by Pandoc container
formats. ZIP extra fields are part of ordinary ZIP local and central file
headers, and generated review/import packets need a bounded native way to carry
small provenance metadata without depending on `ZipArchive`, `zip`, `unzip`, or
office tooling. ZIP64 remains intentionally out of scope for this bounded
reader/writer.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `zip`, `unzip`,
ZipArchive, TeX/PDF engine, external template engine, browser renderer, media
player, or online service was executed.

## Verification

- Baseline `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 210 assertions, 0 failures`.
- Red-first `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result before implementation: `1 test files, 213 assertions, 2 failures`.
  - Failures: generated writer `extraFieldData` was ignored, and invalid
    writer extra-field metadata did not throw.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 219 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests > /tmp/pandoc-lane-tests-20260605T013446Z.out && printf 'pass_lines=' && rg -c '^PASS ' /tmp/pandoc-lane-tests-20260605T013446Z.out && tail -n 1 /tmp/pandoc-lane-tests-20260605T013446Z.out`
  - Result: `pass_lines=502`, `19 test files, 5243 assertions, 0 failures`.
- `php -l lanes/pandoc/src/ZipPackage.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json metadata valid\n";'`
  - Result: `pandoc json metadata valid`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage` writer, `ZipPackageEntry` extra-field parser, and in-process CRC
and DEFLATE paths. It does not require `ZipArchive`, external `zip`/`unzip`,
Pandoc, Haskell runners, office tools, TeX/PDF engines, browser renderers, or
online services.

## Non-Overlap

This does not repeat accepted central-directory parsing, data-descriptor
handling, base package writing, modified DOS timestamps, extended timestamp
reading, NTFS timestamp parsing, Unicode path/comment decoding, ZIP64
extra-field rejection, Unix symlink rejection, drive-letter path rejection, OPC
content-types or relationship graph behavior, archive compression stream
handling, DOCX/ODT/EPUB body parsing, PDF engine handoff diagnostics, syntax
highlighting, doctemplate rendering, YAML metadata, CSL/BibTeX, table geometry,
math/TeX, charset/Unicode text helpers, XML/HTML DOM handling, or legacy
DOC/CFB slices. It only adds bounded generated-package custom extra-field
emission and validation.

## Follow-Up

Keep full ZIP64 large-archive support, AES/encrypted archives, central-directory
encryption, non-deflate compression methods, executable permission policy,
writer-side split central/local extra-field policies, and broader malformed
extra-field/path corpus coverage as separate bounded ZIP package slices if
concrete Pandoc fixtures require them.
