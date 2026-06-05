# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T010227Z`

Base accepted HEAD: `70e9bdea1f1089cd9383d550be07b1b0df456263`

## Implementation

- Added bounded ZIP package path-safety preflight for Windows drive-letter entry
  names before DOCX/EPUB/ODT package parts are exposed.
- `ZipPackage::assertSafePartName()` now rejects names beginning with
  `A:`-style drive prefixes, covering:
  - raw central-directory entry names;
  - generated `ZipPackage::fromParts()` package entries;
  - decoded Info-ZIP Unicode Path `0x7075` metadata.
- Updated the WordPress ZIP package preflight example so drive-letter media
  paths are classified as rejected before import, alongside the existing
  symlink and ZIP64 policies.

## Source Truth

This stays inside the shared ZIP package support row for richer Pandoc
conversion. ZIP entry names used as package part names must be relative paths
inside the package, not host filesystem paths. A `C:...` drive-relative path is
unsafe for WordPress import preflight because it can look like a media part while
remaining host-path-shaped if later handed to attachment extraction or review
tooling.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `zip`, `unzip`,
TeX/PDF engine, external template engine, browser renderer, media player, or
online service was executed.

## Verification

- Baseline `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 207 assertions, 0 failures`.
- Red-first `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result before implementation: `1 test files, 205 assertions, 1 failures`.
  - Failure: `Expected exception RuntimeException was not thrown` for the new
    drive-letter path preflight case.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 210 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 4971 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests > /tmp/pandoc-lane-tests-20260605T010227Z.out && printf 'pass_lines=' && rg -c '^PASS ' /tmp/pandoc-lane-tests-20260605T010227Z.out && tail -n 1 /tmp/pandoc-lane-tests-20260605T010227Z.out`
  - Result: `pass_lines=484`, `19 test files, 4971 assertions, 0 failures`.
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
`ZipPackage` name validator that is already shared by reader lookup, package
building, central-directory parsing, and Info-ZIP Unicode path decoding. It does
not require `ZipArchive`, external `zip`/`unzip`, Pandoc, Haskell runners,
office tools, TeX/PDF engines, browser renderers, or online services.

## Non-Overlap

This does not repeat accepted central-directory parsing, data-descriptor
handling, package writing, extended timestamp parsing, NTFS timestamp parsing,
Unicode path/comment decoding, ZIP64 extra-field rejection, Unix symlink
rejection, OPC content-types or relationship graph behavior, archive
compression stream handling, DOCX/ODT/EPUB body parsing, PDF engine handoff
diagnostics, syntax highlighting, doctemplate rendering, YAML metadata,
CSL/BibTeX, table geometry, math/TeX, charset/Unicode text helpers, XML/HTML
DOM handling, or legacy DOC/CFB slices. It only adds bounded drive-letter path
rejection in the ZIP package layer.

## Follow-Up

Keep full ZIP64 large-archive support, AES/encrypted archives,
central-directory encryption, non-deflate compression methods, executable
permission policy, and broader malformed extra-field/path corpus coverage as
separate bounded ZIP package slices if concrete Pandoc fixtures require them.
