# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T155515Z`
Base accepted HEAD: `929622be0ce051a6759300944f309b9dcb35c3a2`
Date: 2026-06-05 UTC

No current pandoc rework note was present under
`.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.

## Behavior

- Added `ZipPackage::extraFieldPreflight()` to summarize central-directory and
  local-header ZIP extra-field IDs per entry and expose duplicate IDs for
  reviewer provenance.
- Added `ZipPackage::assertNoDuplicateExtraFieldIds()` so strict DOCX/EPUB/ODT
  and WordPress media review paths can reject ambiguous duplicate extra-field
  metadata before trusting generated package media handoffs.
- Updated the WordPress ZIP package preflight smoke to report duplicate
  extra-field policy and the conflicting media entry.

Source truth: Pandoc consumes DOCX, ODT, and EPUB as ZIP-backed containers
before document conversion. ZIP extra fields carry timestamp, Unicode, and
vendor provenance. A bounded native PHP reader should keep package inspection
available while making duplicate extra-field IDs visible and rejectable for
strict import policies, without invoking external archive tools.

## Evidence

- Baseline before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 428 assertions, 0 failures`.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 451 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  passed with `zip package writer preflight self-test passed`.
- Syntax checks passed for `lanes/pandoc/src/ZipPackage.php`,
  `lanes/pandoc/tests/ZipPackageTest.php`, and
  `lanes/pandoc/examples/wordpress-zip-package-preflight.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.

Root harness: not run - isolated micro-slice.

## Delta

- Focused ZIP package PASS cases: `55 -> 56`.
- Focused ZIP package assertions: `428 -> 451` (`+23`).
- Manifest mapped checks: `1439 -> 1440`.
- ZIP package support cases: `21 -> 22`.
- ZIP package core assertions: `131 -> 154`.
- Lane PHP pass count: `984 -> 985`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`ZipPackage`, `ZipPackageEntry`, in-process CRC/DEFLATE handling, package
preflight example, and focused PHP test harness.

No Pandoc, Cabal solver/build/test command, Haskell runner, `ZipArchive`,
`zip`/`unzip`, Word, LibreOffice, external archive tool, external office tool,
external converter, online sanitizer, or online service was executed.

Full upstream runner parity remains gated on hydrating the Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` and preparing non-mutating Cabal
runner evidence for `test-pandoc` and `test-pandoc-lua-engine`.

## Non-Overlap

This does not repeat existing ZIP central/local metadata, timestamp,
data-descriptor, unsupported compression, deflate option flag, encryption,
Unix symlink, drive-letter, ZIP64, local-layout, central-directory signature,
or creator host-system slices. It owns only duplicate central/local ZIP
extra-field ID provenance and strict duplicate-ID rejection.

## Follow-Up

Keep full ZIP64 large-archive support, spanning archives, non-deflate
decompressor implementation, AES/encrypted payload handling, cryptographic
central-directory signature validation, reader-level default archive policy
wiring, and package-specific duplicate-extra-field policy integration as
separate bounded ZIP package slices unless a concrete DOCX/ODT/EPUB fixture
requires one earlier.
