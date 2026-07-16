# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260606T030546Z`
Base accepted HEAD: `5bc3fade84914b1cfb203bafe4ff5b33b0e2ffc3`
Date: 2026-06-06 UTC

No current Pandoc rework note was present under
`.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.

## Behavior

- `ZipPackage` now validates raw central-directory names and comments as
  UTF-8 before consulting Info-ZIP Unicode path/comment extra fields when the
  ZIP general-purpose UTF-8 flag is set.
- UTF-8 flagged entries now reject Info-ZIP Unicode path or comment extras that
  do not match the already-UTF-8 header text.
- Matching UTF-8 header text plus matching Info-ZIP Unicode extras remains
  readable and keeps the existing `info-zip-unicode-*` provenance labels.
- The WordPress ZIP preflight smoke now reports rejection policy for
  contradictory UTF-8 path and comment metadata before Office/EPUB media bytes
  are exposed as importable attachments.

## Source Truth

Pandoc consumes DOCX, ODT, and EPUB as ZIP-backed packages before document
conversion. ZIP general-purpose bit 11 marks file names and comments as UTF-8.
Info-ZIP Unicode path/comment extras are compatibility metadata for older raw
header encodings. A bounded native package reader should not let those extras
mask invalid UTF-8 bytes or silently rename an already-UTF-8 flagged package
part or comment.

No Pandoc, Cabal build, Haskell runner, `ZipArchive`, zip/unzip, Word,
LibreOffice, external archive tool, external office tool, browser renderer,
online sanitizer, online service, or live provider test was executed.

## Verification

Baseline focused ZIP package verification before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 630 assertions, 0 failures
```

Red-first focused ZIP package verification after adding the new case and before
the implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 631 assertions, 1 failures
```

Focused ZIP package verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 641 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test
zip package writer preflight self-test passed
```

Root harness: not run - isolated micro-slice.

## Delta

- Focused ZIP package test cases: `64 -> 65`.
- Focused ZIP package assertions: `630 -> 641` (`+11`).
- Manifest mapped checks: `1618 -> 1619`.
- `zipPackageCoreSupportCases`: `21 -> 22`.
- `zipPackageCoreAssertions`: `131 -> 142`.
- Lane PHP pass count: `1168 -> 1169`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`,
`ZipPackageEntry`, package fixture builders, focused PHP tests, and the
existing WordPress ZIP package preflight smoke. Full upstream runner parity
remains gated on hydrating the Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal project/package files
and Haskell Tasty executable builds.

## Non-Overlap

This does not repeat accepted ZIP central-directory parsing, local entry order,
data descriptors, stored-first mimetype checks, CRC/local-header integrity,
central/local extra-field parsing, duplicate extra-field IDs, central/local
extra-field ID mismatch preflight, extended or NTFS timestamp mismatch
rejection, ZIP64 extra-field or descriptor rejection, Unix symlink/special-file
rejection, central-directory digital-signature provenance, unsupported
compression-method preflight, deflate trailing-byte validation, encrypted-flag
rejection, DOS directory-attribute checks, or package path hierarchy collision
preflight.

It owns only UTF-8 flag consistency with raw header text and Info-ZIP Unicode
path/comment extras.

## Follow-Up

Keep full ZIP64 large-archive support, spanning archives, AES/encrypted payload
support, non-deflate decompressor implementation, cryptographic central
directory signature validation, and strict package-reader default policy wiring
as separate bounded ZIP/package slices.
