# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T152156Z`
Base accepted HEAD: `6b3aab79916239f37aedcd25bf440809e9645e6e`
Date: 2026-06-05 UTC

No current pandoc rework note was present under
`.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.

## Behavior

- Added `ZipPackage::creatorHostSystemPreflight()` for central-directory
  `version made by` host-system provenance.
- Added `ZipPackage::assertKnownCreatorHostSystems()` so strict DOCX/EPUB/ODT
  and WordPress media review paths can reject unknown creator host-system ids
  before exposing imported package media bytes.
- Updated the WordPress ZIP package preflight smoke to report generated creator
  hosts and the unknown-host rejection policy.

Source truth: Pandoc consumes DOCX, ODT, and EPUB as ZIP-backed containers.
The central-directory `version made by` host-system byte is package metadata,
not document content. This bounded native PHP package reader does not need
external archive tools to preserve known creator provenance and flag unknown
creator-system entries for import review.

## Evidence

- Baseline before adding the new case:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 405 assertions, 0 failures`.
- Red-first after adding the focused case:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  failed with `1 test files, 405 assertions, 1 failures` because
  `creatorHostSystemPreflight()` was undefined.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 428 assertions, 0 failures`.
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

- Focused ZIP package PASS cases: `54 -> 55`.
- Focused ZIP package assertions: `405 -> 428` (`+23`).
- Manifest mapped checks: `1425 -> 1426`.
- ZIP package support cases: `21 -> 22`.
- ZIP package core assertions: `131 -> 154`.
- Lane PHP pass count: `970 -> 971`.

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
or OPC relationship slices. It owns only central-directory creator host-system
provenance and strict unknown-host rejection for shared ZIP package preflight.

## Follow-Up

Keep full ZIP64 large-archive support, spanning archives, non-deflate
decompressor implementation, AES/encrypted payload handling, cryptographic
central-directory signature validation, and reader-level default archive policy
wiring as separate bounded ZIP package slices unless a concrete DOCX/ODT/EPUB
fixture requires one earlier.
