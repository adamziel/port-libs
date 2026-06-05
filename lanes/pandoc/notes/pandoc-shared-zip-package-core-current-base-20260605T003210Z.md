# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T003210Z`

Base accepted HEAD: `3826d9c5fc66c5a6b04da9f4dfd696372a0c022a`

## Implementation

- Added bounded ZIP64 extra-field preflight for Pandoc DOCX/EPUB/ODT-style ZIP
  package containers.
- `ZipPackageEntry` now rejects top-level ZIP64 Extended Information extra
  records (`0x0001`) when parsing central-directory or local-header extra
  fields.
- The existing reader already rejects ZIP64 EOCD/sentinel sizes and offsets;
  this slice closes the remaining metadata path where an otherwise 32-bit
  entry could carry ZIP64 extra metadata and still be exposed to package
  readers.
- Updated the WordPress ZIP package preflight example so ZIP64-marked media
  entries are classified as rejected before import, alongside existing symlink,
  timestamp, Unicode path, and archive packet checks.

## Source Truth

This stays inside the shared ZIP package support row for richer Pandoc
conversion. ZIP64 Extended Information is a ZIP extra field used for 64-bit
sizes and offsets; this bounded native package reader does not implement ZIP64
large-archive support, so the safe local contract is to reject that metadata
before Office/EPUB/ODT media or document bytes are treated as importable.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `zip`, `unzip`,
TeX/PDF engine, external template engine, browser renderer, media player, or
online service was executed.

## Verification

- Baseline `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 203 assertions, 0 failures`.
- Red-first `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result before implementation: `1 test files, 204 assertions, 1 failures`.
  - Failure: central-directory ZIP64 extra metadata was accepted.
- `php -l lanes/pandoc/src/ZipPackageEntry.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json metadata valid\n";'`
  - Result: `pandoc json metadata valid`.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 207 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 4,681 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests > /tmp/pandoc-lane-tests-20260605T003210Z.out && printf 'pass_lines=' && rg -c '^PASS ' /tmp/pandoc-lane-tests-20260605T003210Z.out && tail -n 1 /tmp/pandoc-lane-tests-20260605T003210Z.out`
  - Result: `pass_lines=467`, `19 test files, 4,681 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`ZipPackageEntry` extra-field parser and the existing `ZipPackage` local-header
validation path. It does not require `ZipArchive`, external `zip`/`unzip`,
Pandoc, Haskell runners, office tools, TeX/PDF engines, browser renderers, or
online services.

## Non-Overlap

This does not repeat accepted central-directory parsing, data-descriptor
handling, package writing, extended timestamp parsing, NTFS timestamp parsing,
Unicode path/comment metadata, Unix symlink rejection, OPC content-types or
relationship graph behavior, archive compression stream handling, DOCX/ODT/EPUB
body parsing, PDF engine handoff diagnostics, syntax highlighting,
doctemplate rendering, YAML metadata, CSL/BibTeX, table geometry, math/TeX,
charset/Unicode text helpers, XML/HTML DOM handling, or legacy DOC/CFB slices.
It only adds bounded ZIP64 extra-field package rejection.

## Follow-Up

Keep full ZIP64 large-archive support, AES/encrypted archives,
central-directory encryption, non-deflate compression methods, executable
permission policy, and broader malformed extra-field corpus coverage as
separate bounded ZIP package slices if concrete Pandoc fixtures require them.
