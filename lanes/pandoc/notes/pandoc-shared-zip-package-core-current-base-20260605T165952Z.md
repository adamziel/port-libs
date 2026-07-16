# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T165952Z`
Base accepted HEAD: `8b8a591af989b05307fdc7897147ffd563bc89d0`
Date: 2026-06-05 UTC

No current pandoc rework note was present under
`.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.

## Behavior

- Added `ZipPackage::readIntegrityPreflight()` to verify every ZIP entry
  through the bounded native `read()` path, including size limits, compression
  method dispatch, DEFLATE inflation, uncompressed-size checks, and CRC32
  verification.
- Added `ZipPackage::assertReadableEntries()` so DOCX, EPUB, ODT, and
  WordPress media handoff paths can reject corrupt package payload bytes before
  treating package entries as importable document or attachment bytes.
- Normalized corrupt deflate payload handling by suppressing PHP warning noise
  from `gzinflate()` and returning the existing controlled
  `RuntimeException`.
- Updated the WordPress ZIP package preflight smoke to report
  `packageReadIntegrity.*` summary fields and `zipPayloadIntegrityPolicy`.

Source truth: Pandoc consumes DOCX, ODT, and EPUB as ZIP-backed containers
before conversion. Central/local metadata can be consistent while a compressed
payload is corrupt, so a bounded native reader needs an all-entry integrity
preflight that checks payload bytes without invoking external archive tools.

## Evidence

- Baseline before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 466 assertions, 0 failures`.
- Red-first focused check after adding the new test and before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  failed with `Call to undefined method PortLibs\Pandoc\ZipPackage::readIntegrityPreflight()`.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 490 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  passed with `zip package writer preflight self-test passed`.

Root harness: not run - isolated micro-slice.

## Delta

- Focused ZIP package PASS cases: `57 -> 58`.
- Focused ZIP package assertions: `466 -> 490` (`+24`).
- Manifest mapped checks: `1465 -> 1466`.
- ZIP package support cases: `21 -> 22`.
- ZIP package core assertions: `131 -> 155`.
- Lane PHP pass count: `1010 -> 1011`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`ZipPackage`, `ZipPackageEntry`, in-process CRC/DEFLATE handling, package
preflight example, and focused PHP test harness.

No Pandoc, Cabal solver/build/test command, Haskell runner, `ZipArchive`,
`zip`/`unzip`, Word, LibreOffice, external archive tool, external office tool,
browser renderer, online sanitizer, or online service was executed.

Full upstream runner parity remains gated on hydrating the Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` and preparing non-mutating Cabal
runner evidence for `test-pandoc` and `test-pandoc-lua-engine`.

## Non-Overlap

This does not repeat existing ZIP central/local metadata, extra-field
duplicate-ID, timestamp, data-descriptor metadata validation, unsupported
compression preflight, deflate option flag, encryption, Unix symlink, drive
letter, ZIP64, local-layout, central-directory signature, creator host-system,
comment policy, or archive-compression slices. It owns only all-entry payload
read-integrity preflight for otherwise parseable packages.

## Follow-Up

Keep reader-level default strict archive policy wiring, full ZIP64
large-archive support, spanning archives, non-deflate decompressor
implementation, AES/encrypted payload handling, and cryptographic
central-directory signature validation as separate bounded ZIP package slices
unless a concrete DOCX/ODT/EPUB fixture requires one earlier.
