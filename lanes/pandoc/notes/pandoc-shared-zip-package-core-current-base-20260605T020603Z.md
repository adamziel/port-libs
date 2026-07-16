# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T020603Z`

Base accepted HEAD: `acb802419160de9958bfd37cbf73eb0342fb23ad`

## Implementation

- Added reader-side ZIP directory-entry payload preflight for DOCX/EPUB/ODT
  package containers.
- `ZipPackage::fromString()` now rejects trailing-slash directory entries when
  the central directory reports non-stored compression, data-descriptor use, or
  nonzero compressed/uncompressed sizes.
- Directory entries that pass central metadata checks now validate their local
  header immediately and reject any payload gap before the next local header or
  central directory.
- The writer already rejected generated directory entries with data; this slice
  closes the equivalent imported-package path before WordPress treats Office
  media entries as safe attachment candidates.
- Updated the WordPress ZIP package preflight example so a raw directory record
  carrying payload bytes is classified as rejected.

## Source Truth

ZIP directory records in package containers are metadata entries, not hidden
file payload carriers. For Pandoc-style DOCX, EPUB, and ODT import preflight,
trailing-slash directory entries should not expose or conceal attachment bytes.
This bounded PHP reader keeps accepting empty stored directory records while
rejecting records that carry data or require data-descriptor interpretation.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `zip`, `unzip`,
ZipArchive, TeX/PDF engine, external template engine, browser renderer, media
player, or online service was executed.

## Verification

- Baseline `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 219 assertions, 0 failures`.
- Red-first `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result before implementation: `1 test files, 220 assertions, 1 failures`.
  - Failure: a trailing-slash ZIP directory entry with payload bytes was
    accepted before import preflight.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 223 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests > /tmp/pandoc-lane-tests-20260605T020603Z.out && printf 'pass_lines=' && rg -c '^PASS ' /tmp/pandoc-lane-tests-20260605T020603Z.out && tail -n 1 /tmp/pandoc-lane-tests-20260605T020603Z.out`
  - Result: `pass_lines=523`, `19 test files, 5554 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
- `php -l lanes/pandoc/src/ZipPackage.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json metadata valid\n";'`
  - Result: `pandoc json metadata valid`.
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage` reader, `ZipPackageEntry` metadata model, local-header validation,
and in-process CRC/DEFLATE paths. It does not require `ZipArchive`, external
`zip`/`unzip`, Pandoc, Haskell runners, office tools, TeX/PDF engines, browser
renderers, or online services.

## Non-Overlap

This does not repeat accepted central-directory parsing, data-descriptor
validation for ordinary files, package writing, modified DOS timestamps,
extended timestamp reading, NTFS timestamp parsing, Unicode path/comment
decoding, ZIP64 extra-field rejection, Unix symlink rejection, drive-letter path
rejection, custom writer extra-field emission, OPC content-types or
relationship graph behavior, archive compression stream handling, DOCX/ODT/EPUB
body parsing, PDF engine handoff diagnostics, syntax highlighting,
doctemplate rendering, YAML metadata, CSL/BibTeX, table geometry, math/TeX,
charset/Unicode text helpers, XML/HTML DOM handling, or legacy DOC/CFB slices.
It only adds bounded imported ZIP directory-payload rejection.

## Follow-Up

Keep full ZIP64 large-archive support, AES/encrypted archives,
central-directory encryption, non-deflate compression methods, executable
permission policy, split local/central extra-field policies, and broader
malformed package-path corpus coverage as separate bounded ZIP package slices
if concrete Pandoc fixtures require them.
